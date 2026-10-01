<?php

namespace App\Domain\Users\Services;

use App\Domain\Users\Data\UpdatePrivacyData;
use App\Domain\Users\Models\PrivacySettings;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Privacy writes (FR-PROFILE-4). The new row is written through to `user:{id}:privacy` and the
 * cached profile is invalidated, so the next request decides on the new row (specs/21 §4).
 */
class PrivacySettingsService
{
    public function __construct(private readonly PrivacyPolicyResolver $resolver) {}

    public function update(User $user, UpdatePrivacyData $data): void
    {
        try {
            // The row lock orders concurrent saves, so the last commit is also the last cache write.
            DB::transaction(function () use ($user, $data): void {
                $owner = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $settings = PrivacySettings::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($owner)->authorize('update', $settings);

                $settings->fill([
                    'profile_visibility' => $data->visibility,
                    'show_coc_accounts' => $data->showCocAccounts,
                    'show_clan' => $data->showClan,
                    'allow_recruitment_contact' => $data->allowRecruitmentContact,
                    'searchable' => $data->searchable,
                ])->save();

                $this->resolver->refresh($user->id);
            });
        } catch (Throwable $e) {
            // A rolled-back save must not leave its row in the cache.
            CacheInvalidator::privacy($user->id);
            $this->resolver->forget($user->id);

            throw $e;
        }

        CacheInvalidator::profile($user->username);
    }
}
