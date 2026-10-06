<?php

namespace App\Http\Controllers\Disputes;

use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\Media\Data\UploadCollectionData;
use App\Domain\Media\Enums\MediaCollection;
use App\Domain\PlayerAccounts\Data\AttachResultData;
use App\Domain\PlayerAccounts\Enums\DisputeRefusal;
use App\Domain\PlayerAccounts\Queries\DisputePartyQuery;
use App\Domain\PlayerAccounts\Services\DisputeService;
use App\Http\Controllers\Accounts\AttachController;
use App\Http\Controllers\Controller;
use App\Http\Data\Disputes\DisputeCreatePageData;
use App\Http\Data\Disputes\DisputeShowPageData;
use App\Http\Requests\Disputes\OpenDisputeRequest;
use App\Http\Requests\Disputes\ReleaseDisputeRequest;
use App\Http\Requests\Disputes\RespondDisputeRequest;
use App\Models\User;
use App\Support\Seo\PageMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The parties' dispute pages (specs/13 §5, P2-16): open one from the attach flow's conflict card,
 * follow it, answer, give the account up or withdraw. A dispute the viewer is not a party to is a
 * 404; refusals come back as form errors in the dispute's own words.
 */
class DisputeController extends Controller
{
    public function create(Request $request, DisputeService $disputes, DisputePartyQuery $parties): Response|RedirectResponse
    {
        $user = $this->user($request);
        $tag = PlayerTag::tryFrom($request->string('tag')->toString()) ?? abort(HttpResponse::HTTP_NOT_FOUND);

        // One running dispute per claimant per tag: the form would only be refused.
        if (($running = $parties->runningFor($user, $tag)) !== null) {
            return to_route('disputes.show', ['ulid' => $running]);
        }

        $preview = $request->session()->get(AttachController::PREVIEW);
        $preview = is_array($preview) && ($preview['tag'] ?? null) === $tag->value ? AttachResultData::from($preview) : null;

        $refusal = $disputes->eligibility($user, $tag);
        $page = new DisputeCreatePageData(
            tag: $tag->value,
            player: $preview?->player,
            refusal: $refusal === null ? null : self::refusalText($refusal),
            evidenceUpload: UploadCollectionData::fromCollection(MediaCollection::Evidence),
            evidenceMax: (int) config('coc.disputes.evidence_max'),
            textMax: (int) config('coc.disputes.text_max'),
            responseDays: (int) config('coc.disputes.holder_response_days'),
        );

        return PageMeta::page('Disputes/Create', $page->toArray(), new PageMeta(title: "Dispute {$tag->value}", noindex: true));
    }

    public function store(OpenDisputeRequest $request, DisputeService $disputes): RedirectResponse
    {
        $result = $disputes->open($this->user($request), $request->tag(), $request->reason(), $request->evidence());

        if ($result->refusal !== null || $result->disputeUlid === null) {
            return back()->withErrors(['reason' => $result->refusal === null ? 'The dispute could not be opened.' : self::refusalText($result->refusal)]);
        }

        return to_route('disputes.show', ['ulid' => $result->disputeUlid])
            ->with('success', 'Your dispute is open. The holder has been told.');
    }

    public function show(Request $request, string $ulid, DisputePartyQuery $parties): Response
    {
        $dispute = $parties->forParty($this->user($request), $ulid);

        $page = new DisputeShowPageData(
            dispute: $dispute,
            evidenceUpload: UploadCollectionData::fromCollection(MediaCollection::Evidence),
            textMax: (int) config('coc.disputes.text_max'),
        );

        return PageMeta::page('Disputes/Show', $page->toArray(), new PageMeta(title: "Dispute {$dispute->tag}", noindex: true));
    }

    public function respond(RespondDisputeRequest $request, string $ulid, DisputeService $disputes): RedirectResponse
    {
        $result = $disputes->respond($this->user($request), $ulid, $request->statement(), $request->evidence());

        if ($result->refusal !== null) {
            return back()->withErrors(['statement' => $result->refusal->label().'.']);
        }

        return back()->with('success', 'Sent to the admins.');
    }

    public function withdraw(Request $request, string $ulid, DisputeService $disputes): RedirectResponse
    {
        $result = $disputes->withdraw($this->user($request), $ulid);

        if ($result->refusal !== null) {
            return back()->with('error', $result->refusal->label().'.');
        }

        return back()->with('success', 'You withdrew your claim.');
    }

    public function release(ReleaseDisputeRequest $request, string $ulid, DisputeService $disputes): RedirectResponse
    {
        $result = $disputes->release($this->user($request), $ulid, $request->currentPassword(), $request->ip());

        if ($result->refusal !== null) {
            return back()->withErrors(['current_password' => $result->refusal->label().'.']);
        }

        return back()->with('success', 'You gave the account up. It now belongs to the other player.');
    }

    /**
     * Opening refusals in words. Whether a holder is leaving the platform or a hidden account is
     * already under review is not the asker's to know (specs/13 §4, P2-16 security review), so
     * both read like an unheld tag: the token is the way in every case.
     */
    private static function refusalText(DisputeRefusal $refusal): string
    {
        return match ($refusal) {
            DisputeRefusal::NotHeld, DisputeRefusal::AlreadyDisputed => 'A dispute cannot be opened for this account now. If it is yours, verify it with your in-game API token.',
            default => $refusal->label().'.',
        };
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
