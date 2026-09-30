<?php

use App\Domain\Media\Enums\MediaVisibility;
use App\Domain\Media\Services\MediaUrlResolver;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;

// Presigning is offline, so a real S3-driver disk can sign against any endpoint without network access.
beforeEach(function () {
    $disk = (string) config('media.disk');
    Storage::forgetDisk($disk);

    config([
        "filesystems.disks.{$disk}" => [
            'driver' => 's3',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'region' => 'auto',
            'bucket' => 'test-bucket',
            'endpoint' => 'http://storage-internal:9000',
            'use_path_style_endpoint' => true,
            'throw' => true,
        ],
        'media.presign_host' => null,
        'media.cdn_url' => 'https://cdn.test/',
    ]);
});

it('signs uploads against the configured endpoint when no presign host is set', function () {
    $signed = app(MediaUrlResolver::class)->presignUpload('quarantine/2026/09/x/original.jpg', 'image/jpeg', Date::now()->addMinutes(5));

    $url = parse_url($signed['url']);

    expect($url['host'].':'.$url['port'])->toBe('storage-internal:9000')
        ->and($url['path'])->toBe('/test-bucket/quarantine/2026/09/x/original.jpg')
        ->and($url['query'])->toContain('X-Amz-Signature=')->toMatch('/X-Amz-Expires=(299|300)\b/')
        ->and($signed['headers'])->toBe(['Content-Type' => 'image/jpeg']);
});

it('signs against the presign host when one is set, instead of rewriting a signed URL', function () {
    config(['media.presign_host' => 'http://localhost:9000']);

    $url = parse_url(app(MediaUrlResolver::class)->presignUpload('quarantine/a/original.jpg', 'image/jpeg', Date::now()->addMinutes(5))['url']);

    expect($url['host'].':'.$url['port'])->toBe('localhost:9000')
        ->and($url['query'])->toContain('X-Amz-Signature=');
});

it('builds public URLs from the CDN origin', function () {
    expect(app(MediaUrlResolver::class)->url('public/avatars/x/full.webp', MediaVisibility::Public))
        ->toBe('https://cdn.test/public/avatars/x/full.webp');
});

it('signs private URLs and reuses them while they are fresh', function () {
    $resolver = app(MediaUrlResolver::class);

    $first = $resolver->url('private/evidence/x/card.webp', MediaVisibility::Private);
    Date::setTestNow(Date::now()->addSeconds(5));
    $second = $resolver->url('private/evidence/x/card.webp', MediaVisibility::Private);

    expect($first)->toContain('X-Amz-Signature=')->and($second)->toBe($first);
});
