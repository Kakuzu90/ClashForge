<?php

namespace App\Http\Controllers\Account;

use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Services\UserStatusService;
use App\Http\Controllers\Controller;
use App\Http\Data\Account\AccountStatusPageData;
use App\Models\User;
use App\Support\Seo\PageMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class SuspendedController extends Controller
{
    public function __invoke(Request $request, UserStatusService $statuses): Response|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $notice = $statuses->notice($user);

        // Once the suspension ends (or for anyone else), the notice has nothing to say.
        if ($notice->status !== UserStatus::Suspended) {
            return redirect()->route('home');
        }

        return PageMeta::page(
            'Account/Suspended',
            AccountStatusPageData::fromNotice($notice)->toArray(),
            new PageMeta(title: 'Account suspended', noindex: true),
        );
    }
}
