<?php

use App\Domain\Media\Enums\MediaFailureReason;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Events\MediaFailed;
use App\Domain\Media\Jobs\ProcessMediaJob;
use App\Domain\Media\Models\Media;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Media\InteractsWithMedia;
use Tests\Support\Media\MediaFiles;

// specs/11 §4 upload suite: polyglot, MIME mismatch, oversized, SVG, decompression bomb, 0-byte,
// wrong magic bytes, metadata stripping, IDOR, client-chosen keys.

uses(InteractsWithMedia::class);

beforeEach(function () {
    $this->fakeMediaStorage();
    Event::fake([MediaFailed::class]);
});

it('quarantines files that are more than an image and keeps them for review', function (string $bytes) {
    $media = $this->uploadedMedia($bytes);

    ProcessMediaJob::dispatchSync($media->id);

    expect($media->refresh()->status)->toBe(MediaStatus::Quarantined)
        ->and($media->failure_reason)->toBe(MediaFailureReason::Suspicious)
        ->and($media->variants()->count())->toBe(0);
    $this->mediaDisk()->assertExists($media->path);
    Event::assertDispatched(MediaFailed::class, fn (MediaFailed $e) => $e->status === MediaStatus::Quarantined);
})->with([
    'jpeg with a zip appended' => fn () => MediaFiles::withAppendedZip(MediaFiles::jpeg()),
    'jpeg with a php payload' => fn () => MediaFiles::jpeg().'<?php system($_GET["c"]); ?>',
    'png with a script tag' => fn () => MediaFiles::png().'<SCRIPT>alert(1)</SCRIPT>',
    'svg declared as an image' => fn () => '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)" width="400" height="400"></svg>',
    'text with wrong magic bytes' => fn () => str_repeat('definitely not an image ', 50),
    'html page' => fn () => '<!doctype html><html><body>hi</body></html>',
]);

it('rejects a decompression bomb from its header, before decoding', function () {
    $media = $this->uploadedMedia(MediaFiles::pngHeaderOnly(30_000, 30_000));

    ProcessMediaJob::dispatchSync($media->id);

    // Undecodable would mean GD was asked to allocate the canvas; TooLarge proves it was not.
    expect($media->refresh()->failure_reason)->toBe(MediaFailureReason::TooLarge);
});

it('rejects animated images', function (string $bytes) {
    $media = $this->uploadedMedia($bytes);

    ProcessMediaJob::dispatchSync($media->id);

    expect($media->refresh()->status)->toBe(MediaStatus::Failed)
        ->and($media->failure_reason)->toBe(MediaFailureReason::Animated);
})->with([
    'animated webp' => fn () => MediaFiles::animatedWebp(),
    'apng' => fn () => MediaFiles::apng(),
]);

it('fails objects whose size differs from the declaration', function (string $stored, int $declared) {
    $media = $this->uploadedMedia($stored, declaredSize: $declared);

    ProcessMediaJob::dispatchSync($media->id);

    expect($media->refresh()->failure_reason)->toBe(MediaFailureReason::SizeMismatch);
    $this->mediaDisk()->assertMissing($media->path);
})->with([
    '0-byte object' => fn () => ['', 1],
    'bigger than declared' => fn () => [MediaFiles::jpeg(), 1_000],
]);

it('strips EXIF, including GPS, from every variant', function () {
    $bytes = MediaFiles::jpegWithGps();
    $probe = tempnam(sys_get_temp_dir(), 'exif');
    file_put_contents($probe, $bytes);
    expect(@exif_read_data($probe, 'GPS'))->toHaveKey('GPSLatitudeRef');
    unlink($probe);

    $media = $this->uploadedMedia($bytes);
    ProcessMediaJob::dispatchSync($media->id);

    expect($media->refresh()->status)->toBe(MediaStatus::Ready);

    foreach ($media->variants as $variant) {
        $contents = (string) $this->mediaDisk()->get($variant->path);

        expect($contents)->not->toContain('Exif')->not->toContain('EXIF')->not->toContain('XMP');
    }
});

it('refuses SVG at the intent step', function () {
    $this->actingAs(User::factory()->create())
        ->postJson('/uploads/intent', ['collection' => 'base_screenshot', 'filename' => 'x.svg', 'size' => 500, 'mime' => 'image/svg+xml'])
        ->assertUnprocessable();
});

it('never lets the client choose the storage key or the row state', function () {
    Queue::fake();

    $response = $this->actingAs(User::factory()->create())->postJson('/uploads/intent', [
        'collection' => 'base_screenshot',
        'filename' => '../../public/bases/evil.jpg',
        'size' => 1000,
        'mime' => 'image/jpeg',
        'path' => 'game/1/units/barbarian.png',
        'key' => 'public/avatars/evil.webp',
        'status' => 'ready',
        'user_id' => 999,
        'visibility' => 'public',
        'attachable_id' => 1,
    ])->assertCreated();

    $media = Media::query()->sole();

    expect($media->path)->toStartWith('quarantine/')
        ->and($media->path)->not->toContain('evil')
        ->and($response->json('uploadUrl'))->toContain('/quarantine/')
        ->and($media->status)->toBe(MediaStatus::Pending)
        ->and($media->user_id)->not->toBe(999)
        ->and($media->attachable_id)->toBeNull()
        ->and($media->original_filename)->toBe('evil.jpg');
});

it('hides one user\'s uploads from another', function () {
    Queue::fake();
    $media = Media::factory()->create();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)->postJson("/uploads/{$media->ulid}/complete")->assertNotFound();
    $this->actingAs($intruder)->getJson("/uploads/{$media->ulid}")->assertNotFound();

    expect($media->refresh()->status)->toBe(MediaStatus::Pending);
    Queue::assertNothingPushed();
});
