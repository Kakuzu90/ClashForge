<?php

namespace App\Http\Controllers\Dev;

use App\Domain\Media\Data\UploadCollectionData;
use App\Domain\Media\Enums\MediaCollection;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Seo\PageMeta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Inertia\Response;

/**
 * End-to-end check of the upload pipeline against local storage (specs/25 Phase 0 exit). Sign-in
 * does not exist until P1-01, so this page signs in a verified dev user. Local environment only:
 * 404 everywhere else, staging included.
 */
class MediaPreviewController extends Controller
{
    public const DEV_USER_EMAIL = 'media-dev@clashcommons.test';

    public function __invoke(Request $request): Response
    {
        abort_unless(app()->environment('local'), 404);

        if ($request->user() === null) {
            Auth::login($this->devUser());
            $request->session()->regenerate();
        }

        return PageMeta::page('Dev/Media', [
            'collections' => array_map(
                fn (MediaCollection $collection): UploadCollectionData => UploadCollectionData::fromCollection($collection),
                MediaCollection::uploadable(),
            ),
        ], new PageMeta(title: 'Media upload check', noindex: true));
    }

    private function devUser(): User
    {
        $user = User::query()->firstOrNew(['email' => self::DEV_USER_EMAIL]);

        if (! $user->exists) {
            $user->forceFill([
                'name' => 'Media dev',
                'password' => Str::password(32),
                'email_verified_at' => Date::now(),
            ])->save();
        }

        return $user;
    }
}
