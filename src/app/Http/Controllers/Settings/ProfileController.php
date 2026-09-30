<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Media\Data\UploadCollectionData;
use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Users\Queries\ProfileChoicesQuery;
use App\Domain\Users\Queries\ProfileReadModel;
use App\Domain\Users\Services\ProfileService;
use App\Http\Controllers\Controller;
use App\Http\Data\Settings\ProfileSettingsPageData;
use App\Http\Requests\Settings\SetAvatarRequest;
use App\Http\Requests\Settings\UpdateProfileRequest;
use App\Models\User;
use App\Support\Seo\PageMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * The owner's profile form (FR-PROFILE-2, FR-PROFILE-3). Authorization runs in ProfileService.
 */
class ProfileController extends Controller
{
    public function edit(Request $request, ProfileReadModel $profiles, ProfileChoicesQuery $choices): Response
    {
        $page = new ProfileSettingsPageData(
            profile: $profiles->forOwner($this->user($request)),
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
