<?php

namespace App\Http\Controllers\Profile;

use App\Domain\PlayerAccounts\Queries\AccountReadModel;
use App\Domain\Users\Data\PublicProfileData;
use App\Domain\Users\Queries\PublicProfileReadModel;
use App\Http\Controllers\Controller;
use App\Http\Data\Profile\ProfileShowPageData;
use App\Models\User;
use App\Support\Seo\PageMeta;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * The public profile (FR-PROFILE-5), server-rendered for search engines (specs/17 §6), and the
 * redirect from a changed name (FR-PROFILE-7). Who may see it is decided by PrivacyPolicyResolver
 * inside the read model.
 */
class ProfileController extends Controller
{
    public function show(Request $request, string $username, PublicProfileReadModel $profiles, AccountReadModel $accounts): Response
    {
        $view = $profiles->find($username, $request->user());

        if ($view === null && ($current = $profiles->redirectFor($username, $request->user())) !== null) {
            // A changed name points at the new one during the hold (FR-PROFILE-7). Not cached, so
            // the end of the hold and a privacy change apply at once.
            return redirect()->route('profile.show', ['username' => $current], 301)->header('Cache-Control', 'no-store');
        }

        // One response for an unknown, hidden, banned or pending-deletion profile (specs/11).
        if ($view === null) {
            return PageMeta::page('Profile/NotFound', meta: new PageMeta(title: 'Profile not found', noindex: true))
                ->toResponse($request)
                ->setStatusCode(404);
        }

        $viewer = $request->user();
        $page = new ProfileShowPageData(
            profile: $view->profile,
            ownAccounts: $view->profile->isOwn && $viewer instanceof User ? $accounts->own($viewer) : null,
        );

        return PageMeta::page('Profile/Show', $page->toArray(), $this->meta($view->profile, $view->indexable))->toResponse($request);
    }

    private function meta(PublicProfileData $profile, bool $indexable): PageMeta
    {
        $name = $profile->displayName ?? $profile->username;
        $title = $profile->displayName === null ? "@{$profile->username}" : "{$profile->displayName} (@{$profile->username})";
        $canonical = route('profile.show', ['username' => $profile->username]);
        $description = $profile->bio === null
            ? "{$name} on ".config('app.name').'.'
            : Str::limit(Str::squish($profile->bio), (int) config('platform.profile.meta_description_max'));

        $sameAs = array_values(array_filter(array_map(fn ($link): ?string => $link->url, $profile->socials)));

        return new PageMeta(
            title: $title,
            description: $description,
            canonical: $canonical,
            image: $profile->avatarUrl512,
            type: 'profile',
            jsonLd: array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Person',
                'name' => $name,
                'alternateName' => "@{$profile->username}",
                'url' => $canonical,
                'image' => $profile->avatarUrl512,
                'description' => $profile->bio,
                'sameAs' => $sameAs === [] ? null : $sameAs,
            ], fn (mixed $value): bool => $value !== null),
            noindex: ! $indexable,
        );
    }
}
