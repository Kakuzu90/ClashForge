<?php

namespace App\Http\Controllers\Accounts;

use App\Domain\PlayerAccounts\Services\AccountOwnershipService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounts\DetachAccountRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The owner's actions on their account page (specs/13 §6, FR-COC-12/13): detach, and make it the
 * featured account. Another user's ulid is a 404.
 */
class AccountOwnershipController extends Controller
{
    public function destroy(DetachAccountRequest $request, string $ulid, AccountOwnershipService $ownership): RedirectResponse
    {
        $user = $this->user($request);
        $tag = $ownership->detach($user, $ulid, (string) $request->validated('current_password'), $request->ip());

        return redirect()->route('profile.show', ['username' => $user->username], 303)
            ->with('success', "{$tag} was removed from your account.");
    }

    public function feature(Request $request, string $ulid, AccountOwnershipService $ownership): RedirectResponse
    {
        $ownership->feature($this->user($request), $ulid);

        return back()->with('success', 'This is now your featured account.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
