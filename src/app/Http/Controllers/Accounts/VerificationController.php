<?php

namespace App\Http\Controllers\Accounts;

use App\Domain\PlayerAccounts\Data\VerifyResultData;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Enums\VerifyOutcome;
use App\Domain\PlayerAccounts\Queries\AccountReadModel;
use App\Domain\PlayerAccounts\Services\VerifyOwnershipService;
use App\Http\Controllers\Controller;
use App\Http\Data\Accounts\VerifiedPageData;
use App\Http\Data\Accounts\VerifyPageData;
use App\Http\Requests\Accounts\ApiTokenRequest;
use App\Models\User;
use App\Support\Seo\PageMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Steps 2 and 3 of the attach flow (specs/18 §6, specs/13 §3 steps 6–8): the token for one of the
 * user's own rows, then the success screen. Another user's ulid is a 404.
 */
class VerificationController extends Controller
{
    public function show(Request $request, string $ulid, AccountReadModel $accounts): Response|RedirectResponse
    {
        $account = $accounts->ownRow($this->user($request), $ulid) ?? abort(HttpResponse::HTTP_NOT_FOUND);

        if ($account->status === CocAccountStatus::Verified) {
            return to_route('accounts.verified', ['ulid' => $ulid]);
        }
        // A holder's token ends a dispute over their own row (specs/13 §5 3a), so a `disputed` row has the page too.
        abort_unless(in_array($account->status, [CocAccountStatus::Unverified, CocAccountStatus::Disputed], true), HttpResponse::HTTP_NOT_FOUND);

        $result = $request->session()->get('verifyResult');
        $page = new VerifyPageData(account: $account, result: is_array($result) ? VerifyResultData::from($result) : null);

        return PageMeta::page('Accounts/Verify', $page->toArray(), new PageMeta(title: 'Verify your account', noindex: true));
    }

    public function verify(ApiTokenRequest $request, string $ulid, VerifyOwnershipService $verification, AccountReadModel $accounts): RedirectResponse
    {
        $user = $this->user($request);

        // A second submit after the first one verified it: show the result, not a refusal.
        if ($accounts->ownRow($user, $ulid)?->status === CocAccountStatus::Verified) {
            return to_route('accounts.verified', ['ulid' => $ulid]);
        }

        $result = $verification->verify($user, $ulid, $request->token());

        return $result->outcome === VerifyOutcome::Verified
            ? self::toVerified($result)
            : to_route('accounts.verify', ['ulid' => $ulid])->with('verifyResult', $result->toArray());
    }

    public function verified(Request $request, string $ulid, AccountReadModel $accounts): Response
    {
        $user = $this->user($request);
        $account = $accounts->ownRow($user, $ulid);
        abort_unless($account?->status === CocAccountStatus::Verified, HttpResponse::HTTP_NOT_FOUND);

        $page = new VerifiedPageData(
            account: $account,
            firstAccount: $request->session()->get('firstAccount') === true,
            profileUsername: $user->username,
        );

        return PageMeta::page('Accounts/Verified', $page->toArray(), new PageMeta(title: 'Account verified', noindex: true));
    }

    /**
     * To the success screen. The reward toast is for the first verified account only (specs/18
     * §4), which is the one that became featured on verification.
     */
    public static function toVerified(VerifyResultData $result): RedirectResponse
    {
        return to_route('accounts.verified', ['ulid' => $result->accountUlid])->with('firstAccount', $result->featured);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
