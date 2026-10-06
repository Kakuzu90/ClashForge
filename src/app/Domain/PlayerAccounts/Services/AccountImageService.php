<?php

namespace App\Domain\PlayerAccounts\Services;

use App\Domain\Media\Data\AttachedMediaData;
use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Services\MediaAttachmentService;
use App\Domain\Media\Services\MediaReadService;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The owner's custom images on an account (FR-COC-11, specs/10 §3 "Attachment", §8): up to
 * `coc.images.max`, counted in `coc_accounts.images_count` under the row's lock, so two uploads
 * racing for the last place cannot both land. Another user's account or media ULID is a 404.
 */
class AccountImageService
{
    public function __construct(
        private readonly MediaAttachmentService $media,
        private readonly MediaReadService $read,
    ) {}

    public function add(User $user, string $accountUlid, string $mediaUlid): void
    {
        DB::transaction(function () use ($user, $accountUlid, $mediaUlid): void {
            $account = $this->lockOwn($user, $accountUlid);

            // Attaching to the same parent again succeeds in Media, so a repeated post (a double
            // submit, a retry) is refused here, before it could be counted twice.
            $attached = array_map(fn (AttachedMediaData $item): string => $item->ulid, $this->read->attachedTo($account, MediaCollection::AccountImage));
            if (in_array($mediaUlid, $attached, true)) {
                throw ValidationException::withMessages(['media' => 'This image is already on this account.']);
            }

            if ($account->images_count >= (int) config('coc.images.max')) {
                throw ValidationException::withMessages(['media' => 'This account already has '.config('coc.images.max').' images. Remove one to add another.']);
            }

            $this->media->attach($user, $mediaUlid, MediaCollection::AccountImage, $account, $account->images_count);
            $account->forceFill(['images_count' => $account->images_count + 1])->save();
        });
    }

    public function remove(User $user, string $accountUlid, string $mediaUlid): void
    {
        DB::transaction(function () use ($user, $accountUlid, $mediaUlid): void {
            $account = $this->lockOwn($user, $accountUlid);

            if ($this->media->detachFrom($account, MediaCollection::AccountImage, $mediaUlid) === 0) {
                throw (new ModelNotFoundException)->setModel(CocAccount::class, [$accountUlid]);
            }
            $account->forceFill(['images_count' => max(0, $account->images_count - 1)])->save();
        });
    }

    private function lockOwn(User $user, string $accountUlid): CocAccount
    {
        $account = CocAccount::query()->where('ulid', $accountUlid)->where('user_id', $user->id)->lockForUpdate()->first()
            ?? throw (new ModelNotFoundException)->setModel(CocAccount::class, [$accountUlid]);
        Gate::forUser($user)->authorize('manageImages', $account);

        return $account;
    }
}
