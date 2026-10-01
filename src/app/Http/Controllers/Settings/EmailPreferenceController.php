<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Notifications\Services\EmailPreferenceService;
use App\Http\Controllers\Controller;
use App\Http\Data\Settings\EmailPreferencesPageData;
use App\Http\Requests\Settings\UpdateEmailPreferencesRequest;
use App\Models\User;
use App\Support\Seo\PageMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class EmailPreferenceController extends Controller
{
    public function edit(Request $request, EmailPreferenceService $preferences): Response
    {
        return PageMeta::page('Settings/Notifications', (new EmailPreferencesPageData($preferences->read($this->user($request))))->toArray(), new PageMeta(title: 'Email preferences', noindex: true));
    }

    public function update(UpdateEmailPreferencesRequest $request, EmailPreferenceService $preferences): RedirectResponse
    {
        $preferences->update($this->user($request), $request->toData());

        return back()->with('success', 'Email preferences saved.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
