<?php

use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Models\Media;
use Illuminate\Support\Facades\Date;
use Tests\Support\Media\InteractsWithMedia;

uses(InteractsWithMedia::class);

// specs/10 §9, §11.3: game assets have no media rows by design, so a reconcile that listed game/
// would delete the whole pack. The exclusion is an allowlist; these tests hold it.

beforeEach(function () {
    $this->fakeMediaStorage();
    Date::setTestNow('2026-09-27 05:00:00');
});

it('never deletes the game asset pack, however many times reconcile runs', function () {
    $pack = ['game/1/units/barbarian.png', 'game/1/townhalls/16.png', 'game/1/manifest.json'];

    foreach ($pack as $key) {
        $this->mediaDisk()->put($key, 'asset');
    }

    foreach (range(1, 3) as $run) {
        $this->artisan('media:reconcile-storage')->assertSuccessful();
        $this->travel(7)->days();
    }

    foreach ($pack as $key) {
        $this->mediaDisk()->assertExists($key);
    }
});

it('ignores keys under any prefix it was not given', function () {
    $keys = ['backups/db.sql.gz', 'publicity/banner.png', 'quarantined/x.jpg', 'root.txt'];

    foreach ($keys as $key) {
        $this->mediaDisk()->put($key, 'bytes');
    }

    $this->artisan('media:reconcile-storage')->assertSuccessful();
    $this->travel(7)->days();
    $this->artisan('media:reconcile-storage')->expectsOutputToContain('scanned')->assertSuccessful();

    foreach ($keys as $key) {
        $this->mediaDisk()->assertExists($key);
    }
});

it('never sweeps or purges attached or quarantined media', function () {
    $attached = Media::factory()->ready()->attached()->create(['expires_at' => Date::now()->subDays(2)]);
    $quarantined = Media::factory()->quarantined()->expired()->create();
    $trashedQuarantine = Media::factory()->quarantined()->create();
    $trashedQuarantine->delete();

    foreach ([$attached, $quarantined, $trashedQuarantine] as $media) {
        $this->mediaDisk()->put($media->path, 'bytes');
    }

    $this->travel(60)->days();
    $this->artisan('media:sweep-orphans')->assertSuccessful();
    $this->artisan('media:purge-deleted')->assertSuccessful();

    expect($attached->refresh()->status)->toBe(MediaStatus::Ready)
        ->and($quarantined->refresh()->status)->toBe(MediaStatus::Quarantined)
        ->and($trashedQuarantine->refresh()->status)->toBe(MediaStatus::Quarantined);

    foreach ([$attached, $quarantined, $trashedQuarantine] as $media) {
        $this->mediaDisk()->assertExists($media->path);
    }
});
