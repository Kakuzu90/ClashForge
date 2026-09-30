<?php

use App\Domain\Media\Enums\MediaFailureReason;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Models\MediaStorageOrphan;
use App\Domain\Media\Models\MediaVariant;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Tests\Support\Media\InteractsWithMedia;

uses(InteractsWithMedia::class);

beforeEach(function () {
    $this->fakeMediaStorage();
    Date::setTestNow('2026-09-27 05:00:00');
});

function nextWeeklyRun(): void
{
    test()->travel(7)->days();
}

it('logs an orphan on first sight and deletes it on the next consecutive run', function () {
    $key = 'public/base_screenshot/01orphan/card.webp';
    $this->mediaDisk()->put($key, 'webp');

    $this->artisan('media:reconcile-storage')->assertSuccessful();

    $this->mediaDisk()->assertExists($key);
    expect(MediaStorageOrphan::query()->find($key))->not->toBeNull();

    nextWeeklyRun();
    $this->artisan('media:reconcile-storage')->assertSuccessful();

    $this->mediaDisk()->assertMissing($key);
    expect(MediaStorageOrphan::query()->count())->toBe(0);
});

it('keeps objects that have a media or variant row, including soft-deleted media', function () {
    $pending = Media::factory()->create();
    $trashed = Media::factory()->uploaded()->create();
    $trashed->delete();
    $variant = MediaVariant::factory()->create();

    foreach ([$pending->path, $trashed->path, $variant->path] as $path) {
        $this->mediaDisk()->put($path, 'bytes');
    }

    $this->artisan('media:reconcile-storage')->assertSuccessful();
    nextWeeklyRun();
    $this->artisan('media:reconcile-storage')->assertSuccessful();

    foreach ([$pending->path, $trashed->path, $variant->path] as $path) {
        $this->mediaDisk()->assertExists($path);
    }
    expect(MediaStorageOrphan::query()->count())->toBe(0);
});

it('forgets an orphan that gains a row before the next run', function () {
    // Variants are written before their rows, so a job in flight looks like an orphan.
    $media = Media::factory()->processing()->create();
    $key = "public/base_screenshot/{$media->ulid}/card.webp";
    $this->mediaDisk()->put($key, 'webp');

    $this->artisan('media:reconcile-storage')->assertSuccessful();
    MediaVariant::factory()->for($media)->create(['path' => $key]);
    nextWeeklyRun();
    $this->artisan('media:reconcile-storage')->assertSuccessful();

    $this->mediaDisk()->assertExists($key);
    expect(MediaStorageOrphan::query()->count())->toBe(0);
});

it('does not delete on a second run that comes too soon after the first', function () {
    $key = 'quarantine/2026/09/01early/original.jpg';
    $this->mediaDisk()->put($key, 'bytes');

    $this->artisan('media:reconcile-storage')->assertSuccessful();
    $this->travel((int) config('media.lifecycle.reconcile_confirm_after_hours') - 1)->hours();
    $this->artisan('media:reconcile-storage')->assertSuccessful();

    $this->mediaDisk()->assertExists($key);
    expect(MediaStorageOrphan::query()->find($key))->not->toBeNull();
});

it('records and deletes nothing on a dry run', function () {
    $key = 'private/evidence/01dry/full.webp';
    $this->mediaDisk()->put($key, 'webp');
    MediaStorageOrphan::factory()->create(['path' => $key]);

    $this->artisan('media:reconcile-storage', ['--dry-run' => true])->assertSuccessful();

    $this->mediaDisk()->assertExists($key);
    expect(MediaStorageOrphan::query()->find($key)?->last_seen_at->lessThan(Date::now()))->toBeTrue();
});

it('alerts on rows whose objects are missing and fails the run', function () {
    Log::spy();
    $ready = Media::factory()->ready()->create();
    MediaVariant::factory()->for($ready)->create();
    $held = Media::factory()->quarantined()->create();
    $kept = Media::factory()->failed(MediaFailureReason::ProcessingError)->create();
    // Transient and deterministic-failure rows are not expected to have an object.
    Media::factory()->create();
    Media::factory()->failed(MediaFailureReason::Undecodable)->create();

    $this->artisan('media:reconcile-storage')->assertFailed();

    Log::shouldHaveReceived('error')->with('media.reconcile.objects_missing', ['missing' => 3])->once();
    foreach ([$ready, $held, $kept] as $media) {
        Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context = []) => $message === 'media.reconcile.object_missing' && $context['media'] === $media->ulid);
    }
});
