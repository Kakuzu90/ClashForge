<?php

use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Events\MediaFailed;
use App\Domain\Media\Events\MediaReady;
use App\Domain\Media\Jobs\ProcessMediaJob;
use App\Domain\Media\Models\Media;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Media\InteractsWithMedia;
use Tests\Support\Media\VideoFiles;

// P3-02, specs/11 (Injection, upload abuse) and specs/10 §4: what an uploader controls never reaches
// a command line, and nothing they embedded survives the re-encode.

uses(InteractsWithMedia::class);

beforeEach(function () {
    VideoFiles::requireFfmpeg();
    $this->fakeMediaStorage();
    Event::fake([MediaReady::class, MediaFailed::class]);
});

it('never puts the uploaded filename on a command line', function (string $filename) {
    $marker = sys_get_temp_dir().'/clashcommons-pwned-'.getmypid();
    @unlink($marker);
    $media = $this->uploadedMedia(VideoFiles::make(), MediaCollection::BaseVideo);
    $media->forceFill(['original_filename' => str_replace('{marker}', $marker, $filename)])->save();

    ProcessMediaJob::dispatchSync($media->id);

    expect($media->refresh()->status)->toBe(MediaStatus::Ready)
        ->and(file_exists($marker))->toBeFalse();
})->with([
    'command substitution' => '$(touch {marker}).mp4',
    'backticks' => '`touch {marker}`.mp4',
    'option' => '-f lavfi -i testsrc {marker}.mp4',
    'separator' => 'a.mp4; touch {marker}',
]);

it('strips container and stream metadata, location included', function () {
    $bytes = VideoFiles::make(['metadata' => ['title' => 'my house', 'comment' => 'call me 555-0100', 'location' => '+48.8584+002.2945/', 'artist' => 'Real Name']]);
    expect(VideoFiles::describe($bytes)['tags'])->toHaveKeys(['title', 'comment', 'location', 'artist']);

    $media = $this->uploadedMedia($bytes, MediaCollection::BaseVideo);
    ProcessMediaJob::dispatchSync($media->id);

    $rendition = (string) Storage::disk((string) config('media.disk'))->get($media->variants()->where('variant', 'video_720p')->sole()->path);
    $described = VideoFiles::describe($rendition);
    $tags = array_merge($described['tags'], ...$described['streamTags']);

    expect($tags)->not->toHaveKeys(['title', 'comment', 'location', 'artist'])
        ->and($rendition)->not->toContain('555-0100')->not->toContain('Real Name')->not->toContain('+48.8584');
});

it('strips stream-level tags too', function () {
    $bytes = VideoFiles::make(['streamMetadata' => [
        's:v:0' => ['title' => 'vsecret', 'handler_name' => 'Pixel of Real Name'],
        's:a:0' => ['title' => 'asecret', 'language' => 'fra'],
    ]]);
    expect(VideoFiles::describe($bytes)['streamTags'][0])->toHaveKey('handler_name', 'Pixel of Real Name');

    $media = $this->uploadedMedia($bytes, MediaCollection::BaseVideo);
    ProcessMediaJob::dispatchSync($media->id);

    $rendition = (string) Storage::disk((string) config('media.disk'))->get($media->variants()->where('variant', 'video_720p')->sole()->path);
    $tags = array_merge(...VideoFiles::describe($rendition)['streamTags']);

    expect($tags)->not->toHaveKey('title')->not->toHaveKey('name')
        ->and($tags['handler_name'] ?? '')->not->toContain('Real Name')
        ->and($tags['language'] ?? 'und')->toBe('und')
        ->and($rendition)->not->toContain('vsecret')->not->toContain('asecret');
});

it('drops a track in a codec outside the allowlist without failing the upload', function () {
    $bytes = VideoFiles::make(['audio' => ['aac', 'ac3']]);
    expect(VideoFiles::describe($bytes)['streams'])->toBe(['video:h264', 'audio:aac', 'audio:ac3']);

    $media = $this->uploadedMedia($bytes, MediaCollection::BaseVideo);
    ProcessMediaJob::dispatchSync($media->id);

    expect($media->refresh()->status)->toBe(MediaStatus::Ready)
        ->and(VideoFiles::describe((string) Storage::disk((string) config('media.disk'))->get($media->variants()->where('variant', 'video_720p')->sole()->path))['streams'])
        ->toBe(['video:h264', 'audio:aac']);
});

it('keeps trailing bytes outside the container out of the rendition', function () {
    $media = $this->uploadedMedia(VideoFiles::make()."<?php system(\$_GET['c']); ?>PK\x05\x06", MediaCollection::BaseVideo);

    ProcessMediaJob::dispatchSync($media->id);

    $rendition = (string) Storage::disk((string) config('media.disk'))->get($media->refresh()->variants()->where('variant', 'video_720p')->sole()->path);
    expect($media->status)->toBe(MediaStatus::Ready)
        ->and($rendition)->not->toContain('<?php');
});

it('does not process another user\'s video through a forged complete', function () {
    $media = Media::factory()->collection(MediaCollection::BaseVideo)->create();
    $other = User::factory()->create();
    $other->forceFill(['verified_accounts_count' => 1])->save();

    $this->actingAs($other)->postJson("/uploads/{$media->ulid}/complete")->assertNotFound();

    expect($media->refresh()->status)->toBe(MediaStatus::Pending);
});
