<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Auth\Enums\StaffAbility;
use App\Domain\Moderation\Queries\SanctionHistoryQuery;
use App\Domain\PlayerAccounts\Enums\DisputeQueueView;
use App\Domain\PlayerAccounts\Queries\DisputeAdminQuery;
use App\Domain\PlayerAccounts\Services\DisputeReviewService;
use App\Domain\PlayerAccounts\Services\DisputeService;
use App\Http\Controllers\Controller;
use App\Http\Data\Admin\AdminDisputeIndexPageData;
use App\Http\Data\Admin\AdminDisputeShowPageData;
use App\Http\Data\Admin\FilterOptionData;
use App\Http\Requests\Admin\DecideDisputeRequest;
use App\Http\Requests\Admin\DisputeFilterRequest;
use App\Models\User;
use App\Support\Seo\PageMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The ownership dispute queue, review page and decision (FR-ADMIN-2 disputes, specs/13 §5 step 4,
 * P2-17). `resolve-disputes` (admin+); a party gets the same 404 as an unknown dispute.
 */
class DisputeController extends Controller
{
    public function index(DisputeFilterRequest $request, DisputeAdminQuery $disputes): Response
    {
        $admin = $this->admin($request);
        $view = $request->view();
        $mine = $request->mine();
        $cursor = $request->cursor();

        $page = new AdminDisputeIndexPageData(
            view: $view->value,
            mine: $mine,
            views: array_map(fn (DisputeQueueView $option) => new FilterOptionData($option->value, $option->label()), DisputeQueueView::cases()),
        );

        return PageMeta::page('Admin/Disputes/Index', [
            ...$page->toArray(),
            'disputes' => Inertia::defer(fn (): array => $disputes->queue($admin, $view, $mine, $cursor)->toArray()),
        ], new PageMeta(title: 'Disputes', noindex: true));
    }

    public function show(Request $request, string $ulid, DisputeReviewService $reviews, SanctionHistoryQuery $sanctions): Response
    {
        Gate::authorize(StaffAbility::ResolveDisputes->value);
        $review = $reviews->review($this->admin($request), $ulid);
        $limit = (int) config('platform.admin.audit_trail_limit');

        $page = new AdminDisputeShowPageData(
            dispute: $review->data,
            claimantSanctions: $sanctions->forUser($review->claimant, $limit),
            holderSanctions: $review->holder === null ? [] : $sanctions->forUser($review->holder, $limit),
        );

        return PageMeta::page('Admin/Disputes/Show', $page->toArray(), new PageMeta(title: "Dispute {$review->data->tag}", noindex: true));
    }

    public function decide(DecideDisputeRequest $request, string $ulid, DisputeService $disputes): RedirectResponse
    {
        $result = $disputes->decide($this->admin($request), $ulid, $request->decision(), $request->note());

        if ($result->refusal !== null) {
            return back()->withErrors(['decision' => $result->refusal->label().'.']);
        }

        return back()->with('success', $request->decision()->label().': done.');
    }

    private function admin(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
