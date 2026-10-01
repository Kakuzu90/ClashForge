<?php

namespace App\Domain\Users\Services;

use App\Domain\Users\Models\PrivacySettings;
use App\Domain\Users\Models\Profile;
use App\Domain\Users\Models\UserStats;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProfileAnonymisationService
{
    public function __construct(private readonly PrivacyPolicyResolver $privacy) {}

    // The deletion service owns the transaction and holds the account lock.
    public function anonymise(User $user): void
    {
        $profile = Profile::query()->where('user_id', $user->id)->lockForUpdate()->first();
        $profile?->forceFill([
            'display_name' => null, 'bio' => null, 'country_code' => null,
            'languages' => [], 'timezone' => null, 'socials' => [], 'avatar_media_id' => null,
        ])->save();

        PrivacySettings::query()->whereKey($user->id)->update((new PrivacySettings)->getAttributes());
        UserStats::query()->whereKey($user->id)->update([
            ...(new UserStats)->getAttributes(), 'recomputed_at' => null,
        ]);

        $username = $user->username;
        DB::afterCommit(function () use ($user, $username): void {
            CacheInvalidator::profile($username);
            CacheInvalidator::privacy($user->id);
            $this->privacy->forget($user->id);
        });
    }
}
