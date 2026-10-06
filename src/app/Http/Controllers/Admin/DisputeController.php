<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Data\AuditLogFilterData;
use App\Domain\Audit\Enums\AuditSubject;
use App\Domain\Audit\Queries\AuditLogQuery;
use App\Domain\Moderation\Queries\SanctionHistoryQuery;
use App\Domain\PlayerAccounts\Enums\DisputeDecision;
use App\Domain\PlayerAccounts\Enums\DisputeQueueView;
use App\Domain\PlayerAccounts\Queries\DisputeAdminQuery;
use App\Domain\PlayerAccounts\Services\DisputeReviewService;
use App\Domain\PlayerAccounts\Services\DisputeService;
use App\Http\Controllers\Controller;
use App\Http\Data\Admin\AdminDisputeIndexPageData;
use App\Http\Data\Admin\AdminDisputeShowPageData;
use App\Http\Data\Admin\AuditTrailEntryData;
use App\Http\Data\Admin\FilterOptionData;
use App\Http\Requests\Admin\DecideDisputeRequest;
use App\Http\Requests\Admin\DisputeFilterRequest;
use App\Models\User;
use App\Support\Seo\PageMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The ownership dispute queue, review page and decision (FR-ADMIN-2 disputes, specs/13 §5 step 4,
 * P2-17). `resolve-disputes` (admin+): the queue is a 403 without it; a dispute is a 404 without it
 * or for a party, the same as an unknown one.
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

    /**
     * Without `resolve-disputes`, or as a party, the review service answers 404 (specs/04 §3).
     */
    public function show(Request $request, string $ulid, DisputeReviewService $reviews, SanctionHistoryQuery $sanctions, AuditLogQuery $audit): Response
    {
        $review = $reviews->review($this->admin($request), $ulid);
        $limit = (int) config('platform.admin.audit_trail_limit');
        $trail = $audit->page(new AuditLogFilterData(subjectId: $review->disputeId, subject: AuditSubject::CocAccountDispute), $limit);

        $page = new AdminDisputeShowPageData(
            dispute: $review->data,
            claimantSanctions: $sanctions->forUser($review->claimant, $limit),
            holderSanctions: $review->holder === null ? [] : $sanctions->forUser($review->holder, $limit),
            auditTrail: array_map(AuditTrailEntryData::fromRecord(...), $trail->entries),
            moreAuditEntries: $trail->olderCursor !== null,
        );

        return PageMeta::page('Admin/Disputes/Show', $page->toArray(), new PageMeta(title: "Dispute {$review->data->tag}", noindex: true));
    }

    public function decide(DecideDisputeRequest $request, string $ulid, DisputeService $disputes): RedirectResponse
    {
        $result = $disputes->decide($this->admin($request), $ulid, $request->decision(), $request->note());

        if ($result->refusal !== null) {
            return back()->withErrors(['decision' => $result->refusal->label().'.']);
        }

        return back()->with('success', match ($request->decision()) {
            DisputeDecision::Transfer => 'The account now belongs to the claimant.',
            DisputeDecision::Deny => 'Claim denied. The holder keeps the account.',
            DisputeDecision::Suspend => 'The account is suspended.',
            DisputeDecision::AskClaimant => 'Asked the claimant for more.',
            DisputeDecision::AskHolder => 'Asked the holder for more.',
        });
    }

    private function admin(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
