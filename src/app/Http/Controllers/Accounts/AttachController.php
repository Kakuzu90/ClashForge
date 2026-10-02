<?php

namespace App\Http\Controllers\Accounts;

use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\PlayerAccounts\Data\AttachResultData;
use App\Domain\PlayerAccounts\Data\VerifyResultData;
use App\Domain\PlayerAccounts\Enums\AttachOutcome;
use App\Domain\PlayerAccounts\Enums\VerifyOutcome;
use App\Domain\PlayerAccounts\Services\AttachAccountService;
use App\Domain\PlayerAccounts\Services\VerifyOwnershipService;
use App\Http\Controllers\Controller;
use App\Http\Data\Accounts\AttachPageData;
use App\Http\Requests\Accounts\PlayerTagRequest;
use App\Http\Requests\Accounts\VerifyTagRequest;
use App\Models\User;
use App\Support\Seo\PageMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Step 1 of the attach flow (specs/18 §6, specs/13 §3–4). The last lookup stays in the session
 * while the user is on that tag, so the confirmation or conflict card survives a reload and a
 * failed token. The holder's name is not kept: it is looked up again on each view, so a privacy
 * change or a ban applies at once. Authorization and limits run in the services.
 */
class AttachController extends Controller
{
    public const PREVIEW = 'accounts.attach.preview';

    public function create(Request $request, AttachAccountService $accounts): Response
    {
        $user = $this->user($request);
        $tag = PlayerTag::tryFrom($request->string('tag')->toString());
        $preview = $request->session()->get(self::PREVIEW);
        $verifyResult = $request->session()->get('verifyResult');

        $preview = is_array($preview) && $tag !== null && ($preview['tag'] ?? null) === $tag->value ? AttachResultData::from($preview) : null;
        if ($preview?->outcome === AttachOutcome::VerifiedElsewhere) {
            $preview->holderUsername = $accounts->holderUsername($user, $tag);
        }

        $page = new AttachPageData(
            tag: $tag->value ?? ($request->filled('tag') ? $request->string('tag')->limit(32, '')->toString() : null),
            preview: $preview,
            verifyResult: is_array($verifyResult) ? VerifyResultData::from($verifyResult) : null,
            block: $accounts->block($user),
        );

        return PageMeta::page('Accounts/Attach', $page->toArray(), new PageMeta(title: 'Attach an account', noindex: true));
    }

    public function preview(PlayerTagRequest $request, AttachAccountService $accounts): RedirectResponse
    {
        $result = $accounts->preview($this->user($request), $request->tag());
        $this->remember($request, $result);

        return to_route('accounts.attach', ['tag' => $result->tag]);
    }

    public function store(PlayerTagRequest $request, AttachAccountService $accounts): RedirectResponse
    {
        $result = $accounts->attach($this->user($request), $request->tag());

        if ($result->accountUlid !== null && in_array($result->outcome, [AttachOutcome::Attached, AttachOutcome::AlreadyAttached], true)) {
            $request->session()->forget(self::PREVIEW);

            return to_route('accounts.verify', ['ulid' => $result->accountUlid]);
        }

        // Refused: the card shows why, from the attach's own answer (it may differ from the lookup).
        $this->remember($request, $result);

        return to_route('accounts.attach', ['tag' => $result->tag]);
    }

    private function remember(Request $request, AttachResultData $result): void
    {
        $request->session()->put(self::PREVIEW, [...$result->toArray(), 'holderUsername' => null]);
    }

    /**
     * The conflict card's token path (specs/13 §4 A): the tag is held by someone else.
     */
    public function verifyTag(VerifyTagRequest $request, VerifyOwnershipService $verification): RedirectResponse
    {
        $tag = $request->tag();
        $result = $verification->verifyTag($this->user($request), $tag, $request->token());

        if ($result->outcome === VerifyOutcome::Verified && $result->accountUlid !== null) {
            $request->session()->forget(self::PREVIEW);

            return VerificationController::toVerified($result);
        }

        return to_route('accounts.attach', ['tag' => $tag->value])->with('verifyResult', $result->toArray());
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
