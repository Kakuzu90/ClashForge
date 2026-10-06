<?php

namespace App\Http\Controllers\Accounts;

use App\Domain\PlayerAccounts\Services\AccountImageService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounts\StoreAccountImageRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The owner's custom images on their account page (FR-COC-11, P2-23). The service authorizes
 * and keeps the count; another user's account or upload is a 404.
 */
class AccountImageController extends Controller
{
    public function store(StoreAccountImageRequest $request, string $ulid, AccountImageService $images): RedirectResponse
    {
        $images->add($this->user($request), $ulid, (string) $request->validated('media'));

        return back(303)->with('success', 'Image added.');
    }

    public function destroy(Request $request, string $ulid, string $media, AccountImageService $images): RedirectResponse
    {
        $images->remove($this->user($request), $ulid, $media);

        return back(303)->with('success', 'Image removed.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
