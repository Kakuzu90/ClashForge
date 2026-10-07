<?php

use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\MediaFailureReason;
use App\Domain\Media\Enums\VariantName;
use App\Domain\Media\Exceptions\MediaRejected;
use App\Domain\Media\Support\Ffmpeg;
use App\Domain\Media\Support\VideoProbe;
use App\Domain\Media\Support\VideoProcessor;
use App\Domain\Media\Support\VideoRules;
use Tests\TestCase;

// P3-02: the parts of video processing that need no ffmpeg (specs/10 §4, §6; specs/11 Injection).

uses(TestCase::class);

function probeJson(array $streams, ?string $duration = '12.5'): string
{
    return json_encode(['streams' => $streams, 'format' => $duration === null ? [] : ['duration' => $duration]]);
}

function probe(array $overrides = []): VideoProbe
{
    return new VideoProbe(...[
        'duration' => 12.5,
        'width' => 1920,
        'height' => 1080,
        'frameRate' => 30.0,
        'videoIndex' => 0,
        'videoCodec' => 'h264',
        'audioIndex' => 1,
        'audioCodec' => 'aac',
        'audioChannels' => 2,
        ...$overrides,
    ]);
}

function fakeMp4(): string
{
    $dir = sys_get_temp_dir().'/clashcommons-video-unit/'.getmypid();
    @mkdir($dir, 0777, true);
    $path = $dir.'/original';
    // An ftyp box is all the signature check reads.
    file_put_contents($path, "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom".str_repeat("\0", 64));

    return $path;
}

function rejection(Closure $run): ?MediaFailureReason
{
    try {
        $run();
    } catch (MediaRejected $e) {
        return $e->reason;
    }

    return null;
}

describe('VideoProbe', function () {
    it('reads the first real video stream and the first audio stream', function () {
        $probe = VideoProbe::fromJson(probeJson([
            ['index' => 0, 'codec_type' => 'video', 'codec_name' => 'mjpeg', 'width' => 300, 'height' => 300, 'disposition' => ['attached_pic' => 1]],
            ['index' => 1, 'codec_type' => 'audio', 'codec_name' => 'mp3', 'channels' => 1],
            ['index' => 2, 'codec_type' => 'video', 'codec_name' => 'hevc', 'width' => 1920, 'height' => 1080],
            ['index' => 3, 'codec_type' => 'audio', 'codec_name' => 'aac', 'channels' => 6],
        ]));

        expect($probe)
            ->videoIndex->toBe(2)->videoCodec->toBe('hevc')
            ->audioIndex->toBe(1)->audioCodec->toBe('mp3')->audioChannels->toBe(1)
            ->duration->toBe(12.5)
            ->width->toBe(1920)->height->toBe(1080);
    });

    it('swaps the sides of a quarter-turn recording', function (array $stream) {
        $probe = VideoProbe::fromJson(probeJson([['index' => 0, 'codec_type' => 'video', 'codec_name' => 'h264', 'width' => 1920, 'height' => 1080, ...$stream]]));

        expect([$probe->width, $probe->height])->toBe([1080, 1920]);
    })->with([
        'display matrix' => [['side_data_list' => [['side_data_type' => 'Display Matrix', 'rotation' => -90]]]],
        'rotate tag' => [['tags' => ['rotate' => '270']]],
    ]);

    it('reads the frame rate fraction, and leaves an unknown one at 0', function (array $stream, float $rate) {
        $probe = VideoProbe::fromJson(probeJson([['index' => 0, 'codec_type' => 'video', 'codec_name' => 'h264', 'width' => 64, 'height' => 64, ...$stream]]));

        expect($probe->frameRate)->toEqualWithDelta($rate, 0.001);
    })->with([
        'ntsc' => [['avg_frame_rate' => '30000/1001'], 29.97],
        'fallback' => [['avg_frame_rate' => '0/0', 'r_frame_rate' => '60/1'], 60.0],
        'unknown' => [[], 0.0],
    ]);

    it('falls back to the stream duration and reports no audio', function () {
        $probe = VideoProbe::fromJson(probeJson([['index' => 0, 'codec_type' => 'video', 'codec_name' => 'h264', 'width' => 640, 'height' => 360, 'duration' => '4.2']], null));

        expect($probe)->duration->toBe(4.2)->audioIndex->toBeNull()->audioCodec->toBeNull();
    });

    it('refuses output that does not describe a playable video', function (string $json) {
        expect(rejection(fn () => VideoProbe::fromJson($json)))->toBe(MediaFailureReason::Undecodable);
    })->with([
        'not json' => 'ffprobe crashed',
        'audio only' => probeJson([['index' => 0, 'codec_type' => 'audio', 'codec_name' => 'aac']]),
        'no duration' => probeJson([['index' => 0, 'codec_type' => 'video', 'codec_name' => 'h264', 'width' => 64, 'height' => 64]], null),
    ]);
});

describe('VideoRules', function () {
    it('accepts a probe within every limit', function () {
        expect(rejection(fn () => VideoRules::check(probe())))->toBeNull()
            ->and(rejection(fn () => VideoRules::check(probe(['audioIndex' => null, 'audioCodec' => null]))))->toBeNull();
    });

    it('counts duration to a tenth of a second', function () {
        $max = (float) config('media.video.max_duration');

        expect(rejection(fn () => VideoRules::check(probe(['duration' => $max + 0.04]))))->toBeNull()
            ->and(rejection(fn () => VideoRules::check(probe(['duration' => $max + 0.06]))))->toBe(MediaFailureReason::TooLong);
    });

    it('checks the codec before the size a header may not give', function () {
        expect(rejection(fn () => VideoRules::check(probe(['videoCodec' => 'mpeg4', 'width' => 0, 'height' => 0]))))->toBe(MediaFailureReason::UnsupportedCodec)
            ->and(rejection(fn () => VideoRules::check(probe(['width' => 0, 'height' => 0]))))->toBe(MediaFailureReason::Undecodable);
    });

    it('refuses a frame rate over the limit', function () {
        $max = (float) config('media.video.max_frame_rate');

        expect(rejection(fn () => VideoRules::check(probe(['frameRate' => $max]))))->toBeNull()
            ->and(rejection(fn () => VideoRules::check(probe(['frameRate' => $max + 1]))))->toBe(MediaFailureReason::FrameRateTooHigh);
    });

    it('refuses codecs outside the allowlist', function (array $overrides) {
        expect(rejection(fn () => VideoRules::check(probe($overrides))))->toBe(MediaFailureReason::UnsupportedCodec);
    })->with([
        'vp9 video' => [['videoCodec' => 'vp9']],
        'opus audio' => [['audioCodec' => 'opus']],
    ]);

    it('bounds the input in either orientation', function () {
        $long = (int) config('media.video.max_long_side');
        $short = (int) config('media.video.max_short_side');

        expect(rejection(fn () => VideoRules::check(probe(['width' => $long, 'height' => $short]))))->toBeNull()
            ->and(rejection(fn () => VideoRules::check(probe(['width' => $short, 'height' => $long]))))->toBeNull()
            ->and(rejection(fn () => VideoRules::check(probe(['width' => $long + 1, 'height' => 100]))))->toBe(MediaFailureReason::TooLarge)
            ->and(rejection(fn () => VideoRules::check(probe(['width' => $short + 1, 'height' => $short + 1]))))->toBe(MediaFailureReason::TooLarge);
    });

    it('scales the short side down to the output size, never up, with even sides', function (array $in, array $out) {
        config(['media.video.output_short_side' => 720]);

        expect(VideoRules::outputSize(...$in))->toBe($out);
    })->with([
        '1080p' => [[1920, 1080], [1280, 720]],
        'portrait' => [[1080, 1920], [720, 1280]],
        'wide phone' => [[2400, 1080], [1600, 720]],
        'small' => [[640, 360], [640, 360]],
        'odd' => [[641, 361], [640, 360]],
    ]);
});

describe('Ffmpeg arguments', function () {
    it('passes argument arrays with the demuxer and protocols pinned', function () {
        $ffmpeg = new Ffmpeg;
        $path = '/tmp/media/01jxyz/original';

        foreach ([
            $ffmpeg->probeArguments($path),
            $ffmpeg->transcodeArguments($path, '/tmp/out.mp4', probe(), 1280, 720, 26),
            $ffmpeg->posterArguments('/tmp/out.mp4', '/tmp/poster.webp', 1.25),
        ] as $args) {
            $input = array_search('-i', $args, true);

            expect($args)->toBeList()->each->toBeString()
                ->and(array_slice($args, $input - 8, 8))->toBe(['-protocol_whitelist', 'file', '-enable_drefs', '0', '-use_absolute_path', '0', '-f', 'mov']);
        }
    });

    it('probes headers only and lets ffmpeg open allowlisted decoders only', function () {
        $ffmpeg = new Ffmpeg;
        $whitelist = fn (array $args): string => $args[array_search('-codec_whitelist', $args, true) + 1];

        expect($ffmpeg->probeArguments('/in'))->toContain('-nofind_stream_info')
            ->and($whitelist($ffmpeg->transcodeArguments('/in', '/out', probe(), 1280, 720, 26)))->toBe(implode(',', config('media.video.decoders')))
            ->and($whitelist($ffmpeg->posterArguments('/out', '/poster', 1.0)))->toBe(implode(',', config('media.video.output_decoders')));
    });

    it('runs ffmpeg niced and time-limited', function () {
        $args = (new Ffmpeg)->transcodeArguments('/in', '/out', probe(), 1280, 720, 26);

        expect(array_slice($args, 0, 4))->toBe(['nice', '-n', (string) config('media.video.nice'), config('media.video.ffmpeg')])
            ->and($args[array_search('-timelimit', $args, true) + 1])->toBe((string) config('media.video.ffmpeg_timelimit'));
    });

    it('maps the chosen streams, scales, and strips the rest', function () {
        $args = (new Ffmpeg)->transcodeArguments('/in', '/out', probe(['videoIndex' => 2, 'audioIndex' => 1, 'audioChannels' => 6]), 1280, 720, 30);
        $joined = implode(' ', $args);

        expect($joined)
            ->toContain('-map 0:2 -map 0:1 -c:a aac')
            ->toContain('-ac '.config('media.video.audio_channels_max'))
            ->toContain('-t '.((int) config('media.video.max_duration') + 1))
            ->toContain('-fs '.((int) config('media.video.max_output_bytes') + 1))
            ->toContain('-fpsmax '.config('media.video.output_max_fps'))
            ->toContain('-vf scale=1280:720,format=yuv420p')
            ->toContain('-c:v libx264 -preset '.config('media.video.preset').' -crf 30')
            ->toContain('-sn -dn -map_metadata -1 -map_chapters -1 -movflags +faststart -f mp4 /out');

        expect(implode(' ', (new Ffmpeg)->transcodeArguments('/in', '/out', probe(['audioIndex' => null]), 1280, 720, 26)))
            ->toContain(' -an ')->not->toContain('-c:a');
    });
});

describe('VideoProcessor', function () {
    it('runs a second, stronger pass when the first output is over the cap', function () {
        config(['media.video.max_output_bytes' => 100]);
        $ffmpeg = Mockery::mock(Ffmpeg::class);
        $ffmpeg->shouldReceive('probe')->andReturn(probe(), probe(['width' => 1280, 'height' => 720, 'duration' => 10.0]));
        $crfs = [];
        $ffmpeg->shouldReceive('transcode')->twice()->andReturnUsing(function ($in, $out, $probe, $w, $h, $crf) use (&$crfs) {
            $crfs[] = [$crf, $w, $h];
            file_put_contents($out, str_repeat('x', $crf === (int) config('media.video.crf') ? 200 : 50));
        });
        $ffmpeg->shouldReceive('poster')->once()->withArgs(fn ($video, $out, $at) => abs($at - 10.0 * config('media.video.poster_at')) < 0.001)
            ->andReturnUsing(fn ($video, $out) => file_put_contents($out, 'webp'));

        $result = (new VideoProcessor($ffmpeg))->process(fakeMp4(), MediaCollection::BaseVideo);

        expect($crfs)->toBe([[(int) config('media.video.crf'), 1280, 720], [(int) config('media.video.crf_retry'), 1280, 720]])
            ->and($result->durationSeconds)->toBe(10.0)
            ->and(array_map(fn ($v) => [$v->name, $v->sizeBytes()], $result->variants))->toBe([[VariantName::Video720p, 50], [VariantName::Poster, 4]]);
    });

    it('refuses an output longer than its container claimed', function () {
        $ffmpeg = Mockery::mock(Ffmpeg::class);
        $ffmpeg->shouldReceive('probe')->andReturn(probe(['duration' => 30.0]), probe(['duration' => (float) config('media.video.max_duration') + 1]));
        $ffmpeg->shouldReceive('transcode')->once()->andReturnUsing(fn ($in, $out) => file_put_contents($out, 'mp4'));
        $ffmpeg->shouldNotReceive('poster');

        expect(rejection(fn () => (new VideoProcessor($ffmpeg))->process(fakeMp4(), MediaCollection::BaseVideo)))->toBe(MediaFailureReason::TooLong);
    });

    it('treats unreadable output as a worker fault to retry, not the uploader\'s file', function () {
        $ffmpeg = Mockery::mock(Ffmpeg::class);
        $ffmpeg->shouldReceive('probe')->once()->andReturn(probe());
        $ffmpeg->shouldReceive('probe')->once()->andThrow(MediaRejected::because(MediaFailureReason::Undecodable, 'bad output'));
        $ffmpeg->shouldReceive('transcode')->once()->andReturnUsing(fn ($in, $out) => file_put_contents($out, 'mp4'));

        (new VideoProcessor($ffmpeg))->process(fakeMp4(), MediaCollection::BaseVideo);
    })->throws(RuntimeException::class, 'unreadable transcode output');
});
