<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Auth\Services\UsernameChangeService;
use App\Domain\Media\Data\UploadCollectionData;
use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Users\Queries\ProfileChoicesQuery;
use App\Domain\Users\Queries\ProfileReadModel;
use App\Domain\Users\Services\ProfileService;
use App\Http\Controllers\Controller;
use App\Http\Data\Settings\ProfileSettingsPageData;
use App\Http\Requests\Settings\ChangeUsernameRequest;
use App\Http\Requests\Settings\SetAvatarRequest;
use App\Http\Requests\Settings\UpdateProfileRequest;
use App\Models\User;
use App\Support\Seo\PageMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * The owner's profile form (FR-PROFILE-2, FR-PROFILE-3) and username change (FR-PROFILE-7).
 * Authorization runs in ProfileService and UsernameChangeService.
 */
class ProfileController extends Controller
{
    public function edit(Request $request, ProfileReadModel $profiles, ProfileChoicesQuery $choices, UsernameChangeService $usernames): Response
    {
        $user = $this->user($request);
        $page = new ProfileSettingsPageData(
            profile: $profiles->forOwner($user),
            username: $usernames->settingsFor($user),
            avatarUpload: UploadCollectionData::fromCollection(MediaCollection::Avatar),
            countries: $choices->countries(),
            languages: $choices->languages(),
            timezones: $choices->timezones(),
            limits: [
                'displayNameMax' => (int) config('platform.profile.display_name_max'),
                'bioMax' => (int) config('platform.profile.bio_max'),
                'languagesMax' => (int) config('platform.profile.languages_max'),
            ],
        );

        return PageMeta::page('Settings/Profile', $page->toArray(), new PageMeta(title: 'Profile settings', noindex: true));
    }

    public function update(UpdateProfileRequest $request, ProfileService $profiles): RedirectResponse
    {
        $profiles->update($this->user($request), $request->toData());

        return back()->with('success', 'Profile saved.');
    }

    public function updateUsername(ChangeUsernameRequest $request, UsernameChangeService $usernames): RedirectResponse
    {
        $username = $request->string('username')->toString();
        $usernames->change($this->user($request), $username, $request->string('current_password')->toString(), $request->ip());
        // Typing the current password counts as confirming it (specs/11, 15-minute window).
        $request->session()->passwordConfirmed();

        $days = (int) config('platform.auth.username_reservation_days');

        return back()->with('success', "Username changed to @{$username}. Links to your old name lead here for {$days} days.");
    }

    public function setAvatar(SetAvatarRequest $request, ProfileService $profiles): RedirectResponse
    {
        $profiles->setAvatar($this->user($request), $request->string('media')->toString());

        return back()->with('success', 'Avatar updated.');
    }

    public function removeAvatar(Request $request, ProfileService $profiles): RedirectResponse
    {
        $profiles->removeAvatar($this->user($request));

        return back()->with('success', 'Avatar removed.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
