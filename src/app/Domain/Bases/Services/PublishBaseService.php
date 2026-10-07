<?php

namespace App\Domain\Bases\Services;

use App\Domain\Bases\Data\PublishBaseData;
use App\Domain\Bases\Data\PublishedBaseData;
use App\Domain\Bases\Enums\BaseModerationState;
use App\Domain\Bases\Enums\BaseStatus;
use App\Domain\Bases\Events\BasePublished;
use App\Domain\Bases\Models\BaseLayout;
use App\Domain\Bases\Models\BaseMetric;
use App\Domain\Bases\Models\BaseTag;
use App\Domain\Bases\Support\BaseLink;
use App\Domain\Bases\Support\LayoutHash;
use App\Domain\Bases\Support\TagName;
use App\Domain\Bases\Support\ThLevel;
use App\Domain\Media\Data\AttachedMediaData;
use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Services\MediaAttachmentService;
use App\Domain\Media\Services\MediaReadService;
use App\Domain\PlayerAccounts\Services\AccountCredits;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Publishing a base (specs/05 §4, FR-BASE-1 to 5, 11, 14). One transaction under the author's row
 * lock: the publish limit, the duplicate rules, the credited account, the media and the tags. A
 * base whose media are all ready is published at once; otherwise it waits in `processing` until
 * `publishIfReady()` sees the last one ready (FR-BASE-5). Refusals are field errors.
 */
class PublishBaseService
{
    public function __construct(
        private readonly MediaAttachmentService $attachments,
        private readonly MediaReadService $media,
        private readonly AccountCredits $accounts,
    ) {}

    public function handle(User $author, PublishBaseData $data): PublishedBaseData
    {
        Gate::forUser($author)->authorize('create', BaseLayout::class);

        $link = $this->link($data->baseLink);
        $tags = $this->tags($data->tags);
        $screenshots = array_values(array_unique($data->screenshots));
        $this->checkFields($data, $screenshots);

        return DB::transaction(function () use ($author, $data, $link, $tags, $screenshots): PublishedBaseData {
            // The author's row first: concurrent publishes by one user queue here, so the limit and
            // the own-duplicate rule are read once each.
            $locked = User::query()->whereKey($author->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($locked)->authorize('create', BaseLayout::class);
            $this->checkLimit($locked);

            $hash = LayoutHash::of($link);
            $this->lockLayout($hash);
            if (BaseLayout::query()->where('user_id', $locked->id)->where('layout_hash', $hash->value)->exists()) {
                throw ValidationException::withMessages(['base_link' => 'You have already published this layout.']);
            }

            $accountId = $this->accounts->creditableId($locked->id, $data->accountUlid)
                ?? throw ValidationException::withMessages(['account' => 'Choose one of your verified accounts.']);

            $base = $this->create($locked, $data, $link, $hash, $accountId);
            $this->attachMedia($locked, $base, $screenshots, $data->video);
            $this->syncTags($locked, $base, $tags);
            (new BaseMetric)->forceFill(['base_layout_id' => $base->id])->save();

            if ($this->mediaReady($base)) {
                $this->publish($base);
            }

            return new PublishedBaseData($base->ulid, $base->slug, $base->status);
        });
    }

    /**
     * Publishes a `processing` base once every attached item is ready (the `MediaReady` listener).
     * A deleted, already published or moderated base is left alone (specs/23 §3), and so is one
     * whose author may no longer publish: a sanction since submitting keeps it off the site
     * (specs/12).
     */
    public function publishIfReady(int $baseId): bool
    {
        $authorId = BaseLayout::query()->whereKey($baseId)->value('user_id');

        if ($authorId === null) {
            return false;
        }

        return DB::transaction(function () use ($baseId, $authorId): bool {
            // The author, then the base: the order `handle()` locks in.
            $author = User::query()->whereKey($authorId)->lockForUpdate()->first();
            $base = BaseLayout::query()->whereKey($baseId)->lockForUpdate()->first();

            if ($author === null || $base === null || $base->status !== BaseStatus::Processing
                || Gate::forUser($author)->denies('create', BaseLayout::class) || ! $this->mediaReady($base)) {
                return false;
            }

            $this->publish($base);

            return true;
        });
    }

    private function link(string $value): BaseLink
    {
        try {
            return BaseLink::from($value);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['base_link' => $e->getMessage()]);
        }
    }

    /**
     * @param  list<string>  $typed
     * @return list<string> normalised and unique
     */
    private function tags(array $typed): array
    {
        $names = [];

        foreach ($typed as $tag) {
            if (($error = TagName::errorFor($tag)) !== null) {
                throw ValidationException::withMessages(['tags' => $error]);
            }
            $names[] = TagName::from($tag)->value;
        }

        $names = array_values(array_unique($names));
        $max = (int) config('bases.tags_max');

        if (count($names) > $max) {
            throw ValidationException::withMessages(['tags' => "Add at most {$max} tags."]);
        }

        return $names;
    }

    /**
     * @param  list<string>  $screenshots
     */
    private function checkFields(PublishBaseData $data, array $screenshots): void
    {
        $titleMax = (int) config('bases.title_max');
        $descriptionMax = (int) config('bases.description_max');
        $screenshotsMax = (int) config('bases.screenshots_max');

        $errors = array_filter([
            'title' => trim($data->title) === '' ? 'Give the base a title.' : (mb_strlen(trim($data->title)) > $titleMax ? "A title has at most {$titleMax} characters." : null),
            'description' => mb_strlen((string) $data->description) > $descriptionMax ? "A description has at most {$descriptionMax} characters." : null,
            'th_level' => ThLevel::errorFor($data->thLevel),
            'screenshots' => count($screenshots) > $screenshotsMax ? "Add at most {$screenshotsMax} screenshots." : null,
        ]);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * The `base-publish` limit (specs/04 §4, FR-BASE-14), counted from the bases themselves, deleted
     * ones included: a refused publish never uses one up, and deleting and republishing still
     * counts (specs/23 §3).
     */
    private function checkLimit(User $author): void
    {
        $created = fn (int $days): int => BaseLayout::withTrashed()->where('user_id', $author->id)->where('created_at', '>=', Date::now()->subDays($days))->count();

        $perDay = (int) config('bases.publish_per_day');
        if ($created(1) >= $perDay) {
            throw ValidationException::withMessages(['base' => "You can publish {$perDay} bases a day. Try again tomorrow."]);
        }

        $perWeek = (int) config('bases.publish_per_week');
        if ($created(7) >= $perWeek) {
            throw ValidationException::withMessages(['base' => "You can publish {$perWeek} bases a week. Try again in a few days."]);
        }
    }

    /**
     * Serialises publishes of one layout across authors, so of two arriving together the later
     * still sees the first and is flagged (specs/23 §3). SQLite runs one writer at a time anyway.
     */
    private function lockLayout(LayoutHash $hash): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ["base_layout:{$hash->value}"]);
        }
    }

    private function create(User $author, PublishBaseData $data, BaseLink $link, LayoutHash $hash, int $accountId): BaseLayout
    {
        $ulid = (string) Str::ulid();
        $title = trim($data->title);
        $titleSlug = trim(Str::limit(Str::slug($title), 90 - strlen($ulid) - 1, ''), '-');
        // Another user's live copy of this layout: published too, but flagged for review (FR-BASE-11).
        $copy = BaseLayout::query()->where('layout_hash', $hash->value)->where('user_id', '!=', $author->id)->exists();

        $base = new BaseLayout([
            'title' => $title,
            'description' => $data->description === null || trim($data->description) === '' ? null : trim($data->description),
            'th_level' => $data->thLevel,
            'category' => $data->category,
            'visibility' => $data->visibility,
        ]);
        $base->forceFill([
            'ulid' => $ulid,
            'slug' => $titleSlug === '' ? $ulid : "{$ulid}-{$titleSlug}",
            'user_id' => $author->id,
            'coc_account_id' => $accountId,
            'base_link' => $link->value,
            'layout_hash' => $hash->value,
            'status' => BaseStatus::Processing,
            'has_video' => $data->video !== null,
            'moderation_state' => $copy ? BaseModerationState::Flagged : BaseModerationState::Clean,
            'flagged_reason' => $copy ? 'duplicate_layout' : null,
        ])->save();

        return $base;
    }

    /**
     * @param  list<string>  $screenshots
     */
    private function attachMedia(User $author, BaseLayout $base, array $screenshots, ?string $video): void
    {
        // Positions count within each collection.
        $items = array_map(fn (string $ulid, int $position): array => [$ulid, MediaCollection::BaseScreenshot, 'screenshots', $position], $screenshots, array_keys($screenshots));
        if ($video !== null) {
            $items[] = [$video, MediaCollection::BaseVideo, 'video', 0];
        }

        foreach ($items as [$ulid, $collection, $field, $position]) {
            try {
                $this->attachments->attach($author, $ulid, $collection, $base, $position, $field);
            } catch (ModelNotFoundException) {
                // Another user's upload reads the same as a missing one (specs/11 IDOR).
                throw ValidationException::withMessages([$field => 'This upload was not found. Upload the file again.']);
            }
        }
    }

    /**
     * Finds or creates each tag, refusing a blocked one, and counts the use (FR-BASE-4). A tag on
     * `bases.suggested_tags` is created as suggested.
     *
     * @param  list<string>  $names
     */
    private function syncTags(User $author, BaseLayout $base, array $names): void
    {
        if ($names === []) {
            return;
        }

        // Inserted in one order everywhere, so two publishes creating the same new tags never wait
        // on each other crosswise.
        sort($names);
        $suggested = array_map(fn (string $tag): string => TagName::from($tag)->value, (array) config('bases.suggested_tags'));
        $now = Date::now();
        // A tag two authors create at once is inserted once; the loser reads the winner's row.
        BaseTag::query()->insertOrIgnore(array_map(fn (string $name): array => [
            'name' => $name,
            'slug' => $name,
            'is_suggested' => in_array($name, $suggested, true),
            'created_by' => $author->id,
            'created_at' => $now,
            'updated_at' => $now,
        ], $names));

        // By name, the unique key the insert above respects (case-insensitive on Postgres).
        $tags = BaseTag::query()->whereIn('name', $names)->orderBy('id')->lockForUpdate()->get();

        if (($blocked = $tags->firstWhere('is_blocked', true)) !== null) {
            throw ValidationException::withMessages(['tags' => "The tag \"{$blocked->name}\" is not allowed."]);
        }

        BaseTag::query()->whereKey($tags->modelKeys())->increment('usage_count');
        $base->tags()->attach($tags->modelKeys());
    }

    private function mediaReady(BaseLayout $base): bool
    {
        $attached = [
            ...$this->media->attachedTo($base, MediaCollection::BaseScreenshot),
            ...$this->media->attachedTo($base, MediaCollection::BaseVideo),
        ];

        return collect($attached)->every(fn (AttachedMediaData $item): bool => $item->status === MediaStatus::Ready);
    }

    private function publish(BaseLayout $base): void
    {
        $base->forceFill(['status' => BaseStatus::Published, 'published_at' => Date::now()])->save();

        DB::afterCommit(fn () => BasePublished::dispatch($base->ulid, $base->user_id));
    }
}
