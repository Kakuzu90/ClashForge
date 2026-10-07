<?php

use App\Domain\Bases\Data\PublishBaseData;
use App\Domain\Bases\Data\PublishedBaseData;
use App\Domain\Bases\Enums\BaseCategory;
use App\Domain\Bases\Enums\BaseModerationState;
use App\Domain\Bases\Enums\BaseStatus;
use App\Domain\Bases\Enums\BaseVisibility;
use App\Domain\Bases\Events\BasePublished;
use App\Domain\Bases\Models\BaseLayout;
use App\Domain\Bases\Models\BaseMetric;
use App\Domain\Bases\Models\BaseTag;
use App\Domain\Bases\Services\PublishBaseService;
use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Events\MediaReady;
use App\Domain\Media\Models\Media;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Enums\ReleaseReason;
use App\Domain\PlayerAccounts\Enums\VerificationMethod;
use App\Domain\PlayerAccounts\Events\CocAccountOwnershipTransferred;
use App\Domain\PlayerAccounts\Events\CocAccountReleased;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

// P3-01: PublishBaseService (specs/05 §4; FR-BASE-1 to 5, 11, 14) and publish-on-media-ready.

beforeEach(function () {
    Date::setTestNow('2026-10-07 12:00:00');
    $this->author = publisher();
    $this->account = CocAccount::factory()->for($this->author)->verified()->featured()->create();
});

/**
 * A user allowed to publish: verified email and one verified CoC account held.
 */
function publisher(): User
{
    return holding(User::factory()->create());
}

function holding(User $user): User
{
    $user->forceFill(['verified_accounts_count' => 1])->save();

    return $user;
}

function layoutLink(string $id = 'TH16:WB:AAAAKgAAAAJ0nZ-ZqxY1'): string
{
    return 'https://link.clashofclans.com/en?action=OpenLayout&id='.rawurlencode($id);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function baseData(array $overrides = []): PublishBaseData
{
    return new PublishBaseData(...[
        'title' => 'Anti-root ring',
        'description' => 'Holds against root rider spam.',
        'thLevel' => 16,
        'category' => BaseCategory::War,
        'baseLink' => layoutLink(),
        'visibility' => BaseVisibility::Public,
        'tags' => ['Ring Base', 'anti_root_rider'],
        ...$overrides,
    ]);
}

function screenshot(User $user, MediaStatus $status = MediaStatus::Ready, MediaCollection $collection = MediaCollection::BaseScreenshot): Media
{
    return Media::factory()->collection($collection)->ready()->create(['user_id' => $user->id, 'status' => $status]);
}

function publish(User $author, PublishBaseData $data): PublishedBaseData
{
    return app(PublishBaseService::class)->handle($author, $data);
}

it('publishes at once when there is no media waiting, credited to the featured account', function () {
    Event::fake([BasePublished::class]);
    $shot = screenshot($this->author);

    $result = publish($this->author, baseData(['screenshots' => [$shot->ulid]]));

    $base = BaseLayout::query()->sole();
    expect($result->status)->toBe(BaseStatus::Published)
        ->and($result->slug)->toBe("{$base->ulid}-anti-root-ring")
        ->and($base)->user_id->toBe($this->author->id)->coc_account_id->toBe($this->account->id)
        ->status->toBe(BaseStatus::Published)->moderation_state->toBe(BaseModerationState::Clean)->has_video->toBeFalse()
        ->and($base->published_at?->toIso8601String())->toBe(now()->toIso8601String())
        ->and($base->layout_hash)->toBe(hash('sha256', 'TH16:WB:AAAAKgAAAAJ0nZ-ZqxY1'))
        ->and($base->base_link)->toBe('https://link.clashofclans.com/en?action=OpenLayout&id=TH16%3AWB%3AAAAAKgAAAAJ0nZ-ZqxY1')
        ->and($base->tags()->pluck('name')->sort()->values()->all())->toBe(['anti-root-rider', 'ring-base'])
        ->and(BaseMetric::query()->sole()->base_layout_id)->toBe($base->id)
        ->and($shot->fresh())->attachable_id->toBe($base->id)->expires_at->toBeNull();
    Event::assertDispatched(BasePublished::class, fn (BasePublished $e) => $e->baseUlid === $base->ulid && $e->userId === $this->author->id);
});

it('waits in processing until the last screenshot is ready (FR-BASE-5)', function () {
    Event::fake([BasePublished::class]);
    $ready = screenshot($this->author);
    $pending = screenshot($this->author, MediaStatus::Processing);

    expect(publish($this->author, baseData(['screenshots' => [$ready->ulid, $pending->ulid]]))->status)->toBe(BaseStatus::Processing);
    Event::assertNotDispatched(BasePublished::class);

    $pending->forceFill(['status' => MediaStatus::Ready])->save();
    event(new MediaReady($pending->ulid, $this->author->id, MediaCollection::BaseScreenshot));

    $base = BaseLayout::query()->sole();
    expect($base->status)->toBe(BaseStatus::Published)->and($base->published_at)->not->toBeNull();
    Event::assertDispatchedTimes(BasePublished::class, 1);

    // A second event for the same base changes nothing.
    event(new MediaReady($pending->ulid, $this->author->id, MediaCollection::BaseScreenshot));
    Event::assertDispatchedTimes(BasePublished::class, 1);
});

it('leaves a deleted or other users\' processing base alone when media becomes ready', function () {
    $pending = screenshot($this->author, MediaStatus::Processing);
    publish($this->author, baseData(['screenshots' => [$pending->ulid]]));
    $other = BaseLayout::factory()->processing()->create();
    BaseLayout::query()->where('user_id', $this->author->id)->firstOrFail()->delete();

    $pending->forceFill(['status' => MediaStatus::Ready])->save();
    event(new MediaReady($pending->ulid, $this->author->id, MediaCollection::BaseScreenshot));

    expect(BaseLayout::withTrashed()->where('user_id', $this->author->id)->sole()->status)->toBe(BaseStatus::Processing)
        ->and($other->fresh()->status)->toBe(BaseStatus::Processing);
});

it('blocks the same author republishing a live layout, but not after deleting it', function () {
    publish($this->author, baseData());

    expect(fn () => publish($this->author, baseData(['baseLink' => 'https://link.clashofclans.com/de?ref=x&action=OpenLayout&id=TH16%3AWB%3AAAAAKgAAAAJ0nZ-ZqxY1'])))
        ->toThrow(ValidationException::class, 'You have already published this layout.');

    BaseLayout::query()->firstOrFail()->delete();
    expect(publish($this->author, baseData())->status)->toBe(BaseStatus::Published);
});

it("flags another user's copy of a layout for review, and publishes it (FR-BASE-11)", function () {
    publish($this->author, baseData());
    $other = publisher();
    CocAccount::factory()->for($other)->verified()->create();

    expect(publish($other, baseData())->status)->toBe(BaseStatus::Published);

    $copy = BaseLayout::query()->where('user_id', $other->id)->sole();
    expect($copy)->moderation_state->toBe(BaseModerationState::Flagged)->flagged_reason->toBe('duplicate_layout')
        ->and(BaseLayout::query()->where('user_id', $this->author->id)->sole()->moderation_state)->toBe(BaseModerationState::Clean);
});

it('limits publishing per rolling day and week, counting deleted bases (FR-BASE-14)', function () {
    config(['bases.publish_per_day' => 2, 'bases.publish_per_week' => 3]);
    publish($this->author, baseData(['baseLink' => layoutLink('TH16:WB:AAAAAAAA0001')]));
    publish($this->author, baseData(['baseLink' => layoutLink('TH16:WB:AAAAAAAA0002')]));
    BaseLayout::query()->firstOrFail()->delete();

    expect(fn () => publish($this->author, baseData(['baseLink' => layoutLink('TH16:WB:AAAAAAAA0003')])))
        ->toThrow(ValidationException::class, 'You can publish 2 bases a day. Try again tomorrow.');

    Date::setTestNow(now()->addDay()->addMinute());
    publish($this->author, baseData(['baseLink' => layoutLink('TH16:WB:AAAAAAAA0003')]));
    expect(fn () => publish($this->author, baseData(['baseLink' => layoutLink('TH16:WB:AAAAAAAA0004')])))
        ->toThrow(ValidationException::class, 'You can publish 3 bases a week. Try again in a few days.');
});

it('never uses up the limit on a refused publish', function () {
    config(['bases.publish_per_day' => 1]);

    expect(fn () => publish($this->author, baseData(['baseLink' => 'https://example.test/base'])))->toThrow(ValidationException::class);
    expect(fn () => publish($this->author, baseData(['tags' => ['ok', '!!!']])))->toThrow(ValidationException::class);
    expect(publish($this->author, baseData())->status)->toBe(BaseStatus::Published);
});

it('credits the chosen account, and only one the author holds', function () {
    $second = CocAccount::factory()->for($this->author)->disputed()->create();
    $unverified = CocAccount::factory()->for($this->author)->create();
    $foreign = CocAccount::factory()->verified()->create();

    publish($this->author, baseData(['accountUlid' => $second->ulid]));
    expect(BaseLayout::query()->sole()->coc_account_id)->toBe($second->id);

    foreach ([$unverified, $foreign] as $i => $account) {
        expect(fn () => publish($this->author, baseData(['accountUlid' => $account->ulid, 'baseLink' => layoutLink("TH16:WB:AAAAAAAAX00{$i}")])))
            ->toThrow(ValidationException::class, 'Choose one of your verified accounts.');
    }
});

it('creates new tags as long-tail ones and counts each use', function () {
    BaseTag::factory()->create(['name' => 'island', 'slug' => 'island', 'is_suggested' => true, 'usage_count' => 4]);

    publish($this->author, baseData(['tags' => ['Island', 'my-own-tag', 'anti-air', 'island']]));

    $tags = BaseTag::query()->orderBy('name')->get()->keyBy('name');
    expect($tags->keys()->all())->toBe(['anti-air', 'island', 'my-own-tag'])
        ->and($tags['island']->usage_count)->toBe(5)
        ->and($tags['my-own-tag'])->usage_count->toBe(1)->is_suggested->toBeFalse()->created_by->toBe($this->author->id)
        ->and($tags['anti-air']->is_suggested)->toBeTrue();
});

it('refuses blocked tags and too many tags', function () {
    BaseTag::factory()->blocked()->create(['name' => 'slur', 'slug' => 'slur']);
    $max = (int) config('bases.tags_max');

    expect(fn () => publish($this->author, baseData(['tags' => ['slur']])))->toThrow(ValidationException::class, 'The tag "slur" is not allowed.')
        ->and(fn () => publish($this->author, baseData(['tags' => array_map(fn ($i) => "tag-{$i}", range(0, $max))])))->toThrow(ValidationException::class, "Add at most {$max} tags.")
        ->and(BaseLayout::query()->count())->toBe(0)
        ->and(BaseTag::query()->count())->toBe(1);
});

it('refuses bad fields as field errors', function (array $overrides, string $field) {
    try {
        publish($this->author, baseData($overrides));
        $this->fail('Expected a validation error.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);
    }
    expect(BaseLayout::query()->count())->toBe(0);
})->with([
    'blank title' => [['title' => '  '], 'title'],
    'long title' => [fn () => ['title' => str_repeat('a', (int) config('bases.title_max') + 1)], 'title'],
    'long description' => [fn () => ['description' => str_repeat('a', (int) config('bases.description_max') + 1)], 'description'],
    'Town Hall too high' => [fn () => ['thLevel' => (int) config('bases.th_max') + 1], 'th_level'],
    'Town Hall too low' => [fn () => ['thLevel' => (int) config('bases.th_min') - 1], 'th_level'],
    'not a game link' => [['baseLink' => 'https://example.test/?action=OpenLayout&id=TH16:WB:AAAAAAAA'], 'base_link'],
]);

it('takes at most the configured screenshots, only the author\'s own, from the right collection', function () {
    $max = (int) config('bases.screenshots_max');
    $mine = collect(range(0, $max))->map(fn () => screenshot($this->author)->ulid)->all();
    $foreign = screenshot(publisher())->ulid;
    $avatar = screenshot($this->author, collection: MediaCollection::Avatar)->ulid;
    $failed = screenshot($this->author, MediaStatus::Failed)->ulid;

    expect(fn () => publish($this->author, baseData(['screenshots' => $mine])))->toThrow(ValidationException::class, "Add at most {$max} screenshots.")
        ->and(fn () => publish($this->author, baseData(['screenshots' => [$foreign]])))->toThrow(ValidationException::class, 'This upload was not found. Upload the file again.')
        ->and(fn () => publish($this->author, baseData(['screenshots' => [$avatar]])))->toThrow(ValidationException::class, 'This upload was made for something else.')
        ->and(fn () => publish($this->author, baseData(['screenshots' => [$failed]])))->toThrow(ValidationException::class, 'This upload is not ready to use.')
        ->and(BaseLayout::query()->count())->toBe(0)
        ->and(Media::query()->whereNotNull('attachable_id')->count())->toBe(0);
});

it('attaches a replay video and marks the base as having one', function () {
    $video = screenshot($this->author, MediaStatus::Processing, MediaCollection::BaseVideo);

    expect(publish($this->author, baseData(['video' => $video->ulid]))->status)->toBe(BaseStatus::Processing)
        ->and(BaseLayout::query()->sole()->has_video)->toBeTrue();
});

it('lets only a verified, active user with a held CoC account publish', function (Closure $user) {
    $user = $user();

    expect(fn () => publish($user, baseData()))->toThrow(AuthorizationException::class)
        ->and(BaseLayout::query()->count())->toBe(0);
})->with([
    'unverified email' => [fn () => holding(User::factory()->unverified()->create())],
    'no verified account' => [fn () => User::factory()->create()],
    'restricted' => [fn () => holding(User::factory()->restricted()->create())],
    'suspended' => [fn () => holding(User::factory()->suspended()->create())],
    'banned' => [fn () => holding(User::factory()->banned()->create())],
    'leaving' => [fn () => holding(User::factory()->pendingDeletion()->create())],
]);

it('keeps the slug to 90 characters and falls back to the ulid alone', function () {
    publish($this->author, baseData(['title' => str_repeat('Long title ', 7)]));
    publish($this->author, baseData(['title' => '城堡', 'baseLink' => layoutLink('TH16:WB:AAAAAAAA0009')]));

    [$long, $bare] = BaseLayout::query()->orderBy('id')->get()->all();
    expect(strlen($long->slug))->toBeLessThanOrEqual(90)->and($long->slug)->toStartWith("{$long->ulid}-long-title")->not->toEndWith('-')
        ->and($bare->slug)->toBe($bare->ulid);
});

it('ignores media that is not a base screenshot or video', function () {
    $pending = screenshot($this->author, MediaStatus::Processing);
    publish($this->author, baseData(['screenshots' => [$pending->ulid]]));
    $pending->forceFill(['status' => MediaStatus::Ready])->save();

    event(new MediaReady($pending->ulid, $this->author->id, MediaCollection::Avatar));

    expect(BaseLayout::query()->sole()->status)->toBe(BaseStatus::Processing);
});

it('keeps a base in processing while one of its items failed', function () {
    $ready = screenshot($this->author);
    $pending = screenshot($this->author, MediaStatus::Processing);
    publish($this->author, baseData(['screenshots' => [$ready->ulid, $pending->ulid]]));

    $pending->forceFill(['status' => MediaStatus::Failed])->save();
    event(new MediaReady($ready->ulid, $this->author->id, MediaCollection::BaseScreenshot));

    expect(BaseLayout::query()->sole()->status)->toBe(BaseStatus::Processing);
});

it('numbers screenshots and the video within their own collection', function () {
    $shots = [screenshot($this->author)->ulid, screenshot($this->author)->ulid];
    $video = screenshot($this->author, collection: MediaCollection::BaseVideo)->ulid;

    publish($this->author, baseData(['screenshots' => $shots, 'video' => $video]));

    expect(Media::query()->whereIn('ulid', [...$shots, $video])->orderBy('id')->pluck('position')->all())->toBe([0, 1, 0]);
});

it('lets the database refuse a second live copy of one author\'s layout, but not after a delete', function () {
    $base = BaseLayout::factory()->for($this->author)->forLayout('TH16:WB:AAAAAAAADB01')->create();

    // In a savepoint: Postgres aborts the surrounding transaction on a violation.
    expect(fn () => DB::transaction(fn () => BaseLayout::factory()->for($this->author)->forLayout('TH16:WB:AAAAAAAADB01')->create()))
        ->toThrow(UniqueConstraintViolationException::class);

    $base->delete();
    expect(BaseLayout::factory()->for($this->author)->forLayout('TH16:WB:AAAAAAAADB01')->create()->exists)->toBeTrue();
});

it("drops a base's credit once its author no longer holds the account, and keeps the base (specs/08 §3.2)", function (Closure $lose) {
    $kept = CocAccount::factory()->for($this->author)->verified()->create();
    publish($this->author, baseData(['accountUlid' => $this->account->ulid]));
    publish($this->author, baseData(['accountUlid' => $kept->ulid, 'baseLink' => layoutLink('TH16:WB:AAAAAAAACR02')]));
    $updated = BaseLayout::query()->orderBy('id')->first()->updated_at;

    $lose($this);

    [$first, $second] = BaseLayout::query()->orderBy('id')->get()->all();
    expect($first)->coc_account_id->toBeNull()->user_id->toBe($this->author->id)
        ->and($first->updated_at->toIso8601String())->toBe($updated->toIso8601String())
        ->and($second->coc_account_id)->toBe($kept->id);
})->with([
    'detach (release)' => [function ($test) {
        $test->account->forceFill(['user_id' => null, 'status' => CocAccountStatus::Released])->save();
        event(new CocAccountReleased($test->account->id, $test->author->id, ReleaseReason::Detach));
    }],
    'superseded by a token (transfer)' => [function ($test) {
        $test->account->forceFill(['status' => CocAccountStatus::Unverified])->save();
        $winner = CocAccount::factory()->verified()->create();
        event(new CocAccountOwnershipTransferred($winner->id, $test->author->id, $winner->user_id, VerificationMethod::ApiToken));
    }],
]);

it('reads its limits from config', function () {
    expect(config('bases'))->toMatchArray([
        'th_min' => 2, 'th_max' => 18, 'title_max' => 80, 'description_max' => 2000, 'tags_max' => 10, 'tag_max_length' => 24,
        'screenshots_max' => 2, 'videos_max' => 1, 'publish_per_day' => 5, 'publish_per_week' => 20,
    ])->and(config('bases.suggested_tags'))->toContain('ring-base', 'anti-air');
});
