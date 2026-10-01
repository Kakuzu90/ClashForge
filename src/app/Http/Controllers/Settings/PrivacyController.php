<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Users\Data\PrivacyFormData;
use App\Domain\Users\Data\VisibilityOptionData;
use App\Domain\Users\Services\PrivacyPolicyResolver;
use App\Domain\Users\Services\PrivacySettingsService;
use App\Http\Controllers\Controller;
use App\Http\Data\Settings\PrivacySettingsPageData;
use App\Http\Requests\Settings\UpdatePrivacyRequest;
use App\Models\User;
use App\Support\Seo\PageMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * The owner's privacy settings (FR-PROFILE-4). Authorization runs in PrivacySettingsService.
 */
class PrivacyController extends Controller
{
    public function edit(Request $request, PrivacyPolicyResolver $privacy): Response
    {
        $user = $this->user($request);

        $page = new PrivacySettingsPageData(
            settings: PrivacyFormData::fromSettings($privacy->settingsFor($user->id)),
            visibilityOptions: VisibilityOptionData::choices(),
            username: $user->username,
        );

        return PageMeta::page('Settings/Privacy', $page->toArray(), new PageMeta(title: 'Privacy settings', noindex: true));
    }

    public function update(UpdatePrivacyRequest $request, PrivacySettingsService $settings): RedirectResponse
    {
        $settings->update($this->user($request), $request->toData());

        return back()->with('success', 'Privacy settings saved.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
