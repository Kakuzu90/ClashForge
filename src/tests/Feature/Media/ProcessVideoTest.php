<?php

use App\Domain\Bases\Data\PublishBaseData;
use App\Domain\Bases\Enums\BaseCategory;
use App\Domain\Bases\Enums\BaseStatus;
use App\Domain\Bases\Enums\BaseVisibility;
use App\Domain\Bases\Models\BaseLayout;
use App\Domain\Bases\Services\PublishBaseService;
use App\Domain\Media\Contracts\MediaProcessor;
use App\Domain\Media\Contracts\MediaScanner;
use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\MediaFailureReason;
use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Events\MediaFailed;
use App\Domain\Media\Events\MediaReady;
use App\Domain\Media\Jobs\ProcessMediaJob;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Services\MediaProcessingService;
use App\Domain\Media\Services\UploadStatusService;
use App\Domain\Media\Support\Ffmpeg;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Media\InteractsWithMedia;
use Tests\Support\Media\MediaFiles;
use Tests\Support\Media\VideoFiles;

// P3-02: replay videos through ProcessMediaJob (specs/10 §3, §4, §6; specs/23 §3, §4).

uses(InteractsWithMedia::class);

beforeEach(function () {
    VideoFiles::requireFfmpeg();
    $this->fakeMediaStorage();
    Event::fake([MediaReady::class, MediaFailed::class]);
});

function processVideo(Media $media): Media
{
    ProcessMediaJob::dispatchSync($media->id);

    return $media->refresh();
}

function rendition(Media $media, string $variant): string
{
    return (string) Storage::disk((string) config('media.disk'))->get($media->variants()->where('variant', $variant)->sole()->path);
}

it('transcodes an mp4 to a 720p h264/aac rendition and a WebP poster', function () {
    $bytes = VideoFiles::make(['width' => 1920, 'height' => 1080, 'seconds' => 3]);
    $media = processVideo($this->uploadedMedia($bytes, MediaCollection::BaseVideo));
    $disk = $this->mediaDisk();

    expect($media->status)->toBe(MediaStatus::Ready)
        ->and($media->mime_type)->toBe('video/mp4')
        ->and($media->extension)->toBe('mp4')
        ->and([$media->width, $media->height])->toBe([1280, 720])
        ->and($media->duration_seconds)->toEqualWithDelta(3.0, 0.1)
        ->and($media->checksum_sha256)->toBe(hash('sha256', $bytes))
        ->and($media->processing_started_at)->not->toBeNull();

    $variants = $media->variants()->get()->keyBy(fn ($v) => $v->variant->value);
    expect($variants->keys()->sort()->values()->all())->toBe(['poster', 'video_720p'])
        ->and($variants['video_720p']->path)->toBe("public/base_video/{$media->ulid}/video_720p.mp4")
        ->and($variants['video_720p']->mime_type)->toBe('video/mp4')
        ->and($variants['video_720p']->size_bytes)->toBe($disk->size($variants['video_720p']->path))
        ->and($variants['poster']->path)->toBe("public/base_video/{$media->ulid}/poster.webp")
        ->and(finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), rendition($media, 'poster')))->toBe('image/webp')
        ->and(getimagesizefromstring(rendition($media, 'poster'))[0])->toBe(1280);

    expect(VideoFiles::describe(rendition($media, 'video_720p')))
        ->streams->toBe(['video:h264', 'audio:aac'])
        ->width->toBe(1280)->height->toBe(720);

    $disk->assertMissing($media->path);
    Event::assertDispatched(MediaReady::class, fn (MediaReady $e) => $e->mediaUlid === $media->ulid && $e->collection === MediaCollection::BaseVideo);
});

it('never scales up and keeps both sides even', function () {
    $media = processVideo($this->uploadedMedia(VideoFiles::make(['width' => 482, 'height' => 270]), MediaCollection::BaseVideo));

    expect([$media->width, $media->height])->toBe([482, 270]);
});

it('keeps a portrait recording upright, short side at 720', function () {
    $media = processVideo($this->uploadedMedia(VideoFiles::make(['width' => 1920, 'height' => 1080, 'rotate' => 90]), MediaCollection::BaseVideo));

    expect([$media->width, $media->height])->toBe([720, 1280])
        ->and(VideoFiles::describe(rendition($media, 'video_720p')))->width->toBe(720)->height->toBe(1280);
});

it('accepts HEVC video and MP3 audio, and outputs h264/aac', function () {
    $media = processVideo($this->uploadedMedia(VideoFiles::make(['video' => 'libx265', 'audio' => ['libmp3lame']]), MediaCollection::BaseVideo));

    expect($media->status)->toBe(MediaStatus::Ready)
        ->and(VideoFiles::describe(rendition($media, 'video_720p'))['streams'])->toBe(['video:h264', 'audio:aac']);
});

it('outputs video only when there is no audio (specs/23 §4)', function () {
    $media = processVideo($this->uploadedMedia(VideoFiles::make(['audio' => []]), MediaCollection::BaseVideo));

    expect($media->status)->toBe(MediaStatus::Ready)
        ->and(VideoFiles::describe(rendition($media, 'video_720p'))['streams'])->toBe(['video:h264']);
});

it('keeps the first audio track and drops the rest and subtitles (specs/23 §4)', function () {
    $bytes = VideoFiles::make(['audio' => ['aac', 'aac', 'aac'], 'subtitle' => true]);
    expect(VideoFiles::describe($bytes)['streams'])->toBe(['video:h264', 'audio:aac', 'audio:aac', 'audio:aac', 'subtitle:mov_text']);

    $media = processVideo($this->uploadedMedia($bytes, MediaCollection::BaseVideo));

    expect(VideoFiles::describe(rendition($media, 'video_720p'))['streams'])->toBe(['video:h264', 'audio:aac']);
});

it('refuses a video over the duration limit without transcoding it', function () {
    config(['media.video.max_duration' => 3]);
    $media = processVideo($this->uploadedMedia(VideoFiles::make(['width' => 64, 'height' => 64, 'seconds' => 4, 'rate' => 2]), MediaCollection::BaseVideo));

    expect($media->status)->toBe(MediaStatus::Failed)
        ->and($media->failure_reason)->toBe(MediaFailureReason::TooLong)
        ->and($media->variants()->count())->toBe(0)
        ->and(app(UploadStatusService::class)->forOwner(User::query()->findOrFail($media->user_id), $media->ulid)->failureMessage)
        ->toBe('This video is longer than 3 seconds. Trim it and upload it again.');

    $this->mediaDisk()->assertMissing($media->path);
});

it('refuses a codec outside the allowlist', function () {
    $media = processVideo($this->uploadedMedia(VideoFiles::make(['video' => 'mpeg4']), MediaCollection::BaseVideo));

    expect($media->status)->toBe(MediaStatus::Failed)
        ->and($media->failure_reason)->toBe(MediaFailureReason::UnsupportedCodec);
});

it('refuses a resolution over the input limit', function () {
    config(['media.video.max_long_side' => 1000]);
    $media = processVideo($this->uploadedMedia(VideoFiles::make(['width' => 1280, 'height' => 720, 'seconds' => 1]), MediaCollection::BaseVideo));

    expect($media->failure_reason)->toBe(MediaFailureReason::TooLarge)
        ->and(MediaFailureReason::TooLarge->message(MediaKind::Video))->toBe('This video is too large. The limit is 1000 × '.config('media.video.max_short_side').' pixels.');
});

it('fails with a message when the output stays over the cap after the second pass', function () {
    config(['media.video.max_output_bytes' => 1000]);
    $media = processVideo($this->uploadedMedia(VideoFiles::make(), MediaCollection::BaseVideo));

    expect($media->status)->toBe(MediaStatus::Failed)
        ->and($media->failure_reason)->toBe(MediaFailureReason::OutputTooLarge)
        ->and($media->variants()->count())->toBe(0)
        ->and($this->mediaDisk()->allFiles('public'))->toBe([]);
});

it('fails an mp4 that ffprobe cannot read', function () {
    $bytes = VideoFiles::make();
    // The signature survives, the movie header does not.
    $media = processVideo($this->uploadedMedia(substr($bytes, 0, 64).str_repeat("\0", 2000), MediaCollection::BaseVideo));

    expect($media->status)->toBe(MediaStatus::Failed)
        ->and($media->failure_reason)->toBe(MediaFailureReason::Undecodable)
        ->and($media->failure_reason->message(MediaKind::Video))->toContain('This video could not be read');
});

it('quarantines something else behind an .mp4 name', function (string $bytes) {
    $media = processVideo($this->uploadedMedia($bytes, MediaCollection::BaseVideo));

    expect($media->status)->toBe(MediaStatus::Quarantined)
        ->and($media->failure_reason)->toBe(MediaFailureReason::Suspicious);
    $this->mediaDisk()->assertExists($media->path);
})->with([
    'an image' => fn () => MediaFiles::jpeg(),
    'a playlist' => fn () => "#EXTM3U\n#EXT-X-TARGETDURATION:10\n#EXTINF:10,\nfile:///etc/passwd\n#EXT-X-ENDLIST\n",
    'a matroska file' => fn () => VideoFiles::make(['format' => 'matroska']),
]);

it('discards the output when the media was deleted while processing (specs/23 §3)', function () {
    $media = $this->uploadedMedia(VideoFiles::make(), MediaCollection::BaseVideo);
    $real = app(MediaProcessor::class);
    $late = Mockery::mock(MediaProcessor::class);
    $late->shouldReceive('process')->once()->andReturnUsing(function ($local, $collection) use ($real, $media) {
        $result = $real->process($local, $collection);
        // The base was deleted, and its media with it (specs/10 §9 cascade).
        Media::query()->whereKey($media->id)->sole()->delete();

        return $result;
    });

    (new MediaProcessingService($late, app(MediaScanner::class)))->process($media->id);

    expect(Media::withTrashed()->find($media->id)->status)->toBe(MediaStatus::Processing)
        ->and($media->variants()->count())->toBe(0)
        ->and($this->mediaDisk()->allFiles('public'))->toBe([]);
    Event::assertNotDispatched(MediaReady::class);
});

it('publishes a base waiting on its video once the video is ready (FR-BASE-5)', function () {
    Event::fake([MediaFailed::class]);
    Date::setTestNow('2026-10-07 12:00:00');
    $author = User::factory()->create();
    $author->forceFill(['verified_accounts_count' => 1])->save();
    CocAccount::factory()->for($author)->verified()->featured()->create();
    $video = $this->uploadedMedia(VideoFiles::make(), MediaCollection::BaseVideo, owner: $author);

    $published = app(PublishBaseService::class)->handle($author, new PublishBaseData(
        title: 'Replay ring',
        description: null,
        thLevel: 16,
        category: BaseCategory::War,
        baseLink: 'https://link.clashofclans.com/en?action=OpenLayout&id=TH16%3AWB%3AAAAAVideo',
        visibility: BaseVisibility::Public,
        tags: [],
        video: $video->ulid,
    ));
    expect($published->status)->toBe(BaseStatus::Processing);

    ProcessMediaJob::dispatchSync($video->id);

    expect(BaseLayout::query()->sole())->status->toBe(BaseStatus::Published)->has_video->toBeTrue();
});

it('refuses a frame rate over the limit', function () {
    config(['media.video.max_frame_rate' => 20]);
    $media = processVideo($this->uploadedMedia(VideoFiles::make(['rate' => 30, 'seconds' => 1]), MediaCollection::BaseVideo));

    expect($media->failure_reason)->toBe(MediaFailureReason::FrameRateTooHigh)
        ->and($media->failure_reason->message(MediaKind::Video))->toBe('This video has too many frames per second. The limit is 20.');
});

it('retries a failed ffmpeg run, then fails it as a processing error in video words', function () {
    $media = $this->uploadedMedia(VideoFiles::make(), MediaCollection::BaseVideo);
    $ffmpeg = Mockery::mock(Ffmpeg::class)->makePartial();
    $ffmpeg->shouldReceive('transcode')->twice()->andThrow(new RuntimeException('ffmpeg transcode failed (137)'));
    app()->instance(Ffmpeg::class, $ffmpeg);
    $service = app(MediaProcessingService::class);

    foreach (range(1, 2) as $run) {
        expect(fn () => $service->process($media->id))->toThrow(RuntimeException::class);
        expect($media->refresh()->status)->toBe(MediaStatus::Processing);
    }
    // The job's failed() hook once its exceptions are spent.
    $service->giveUp($media->id);

    expect($media->refresh())
        ->status->toBe(MediaStatus::Failed)
        ->failure_reason->toBe(MediaFailureReason::ProcessingError)
        ->processing_attempts->toBe(2)
        ->and($media->failure_reason->message($media->kind))->toBe('Something went wrong while processing this video. Try uploading it again.');
    // Kept for media:retry-failed.
    $this->mediaDisk()->assertExists($media->path);
});

it('keeps the worst run inside the job timeout', function () {
    $video = config('media.video');

    expect(2 * $video['probe_timeout'] + 2 * $video['transcode_timeout'] + $video['poster_timeout'])
        ->toBeLessThan((int) config('media.processing.timeout'));
});
