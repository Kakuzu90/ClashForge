<?php

namespace App\Domain\Media\Services;

use App\Domain\Media\Enums\MediaVisibility;
use DateTimeInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;

/**
 * Every media URL the app hands out (specs/10 §2.1, §7): CDN URLs for public/, cached signed GETs
 * for private objects, presigned PUTs for uploads.
 *
 * Signed URLs cover the Host header, so rewriting the host of a signed URL invalidates it. When
 * `media.presign_host` is set (local dev: the browser cannot resolve the in-network endpoint),
 * URLs are signed against that host instead.
 */
class MediaUrlResolver
{
    public function url(string $path, MediaVisibility $visibility): string
    {
        return $visibility === MediaVisibility::Public
            ? $this->publicUrl($path)
            : $this->privateUrl($path);
    }

    public function publicUrl(string $path): string
    {
        $cdn = config('media.cdn_url');

        if (is_string($cdn) && $cdn !== '') {
            return rtrim($cdn, '/').'/'.ltrim($path, '/');
        }

        return $this->disk()->url($path);
    }

    /**
     * Cached for slightly less than its lifetime so a page of private items does not re-sign each time.
     */
    public function privateUrl(string $path): string
    {
        $ttl = (int) config('media.signed_url_ttl');
        $cacheFor = max(1, $ttl - (int) config('media.signed_url_cache_margin'));

        return Cache::remember(
            'media:signed-get:'.sha1($path),
            $cacheFor,
            fn (): string => $this->signingDisk()->temporaryUrl($path, Date::now()->addSeconds($ttl)),
        );
    }

    /**
     * @return array{url: string, headers: array<string, string>}
     */
    public function presignUpload(string $path, string $contentType, DateTimeInterface $expiresAt): array
    {
        $signed = $this->signingDisk()->temporaryUploadUrl($path, $expiresAt, ['ContentType' => $contentType]);

        return [
            'url' => (string) $signed['url'],
            'headers' => ['Content-Type' => $contentType],
        ];
    }

    private function disk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter */
        return Storage::disk((string) config('media.disk'));
    }

    private function signingDisk(): FilesystemAdapter
    {
        $host = config('media.presign_host');

        if (! is_string($host) || $host === '') {
            return $this->disk();
        }

        $name = (string) config('media.disk');
        /** @var array<string, mixed> $config */
        $config = config("filesystems.disks.{$name}");

        /** @var FilesystemAdapter&Filesystem */
        return Storage::build(['endpoint' => $host] + $config);
    }
}
