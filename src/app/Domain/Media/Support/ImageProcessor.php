<?php

namespace App\Domain\Media\Support;

use App\Domain\Media\Contracts\MediaProcessor;
use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\MediaFailureReason;
use App\Domain\Media\Enums\VariantName;
use App\Domain\Media\Exceptions\MediaRejected;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Throwable;

/**
 * Images: signature → allowlist → dimensions (before decode) → animation → GD decode → WebP
 * variants with metadata stripped (specs/10 §3–5).
 */
final class ImageProcessor implements MediaProcessor
{
    public function process(string $localPath, MediaCollection $collection): ProcessedMedia
    {
        $mimeType = FileInspector::mimeType($localPath);

        /** @var array<string, string> $allowed */
        $allowed = config('media.image.mimes');

        // Declared as an image (the intent allowlist) but something else underneath.
        if (! array_key_exists($mimeType, $allowed)) {
            throw MediaRejected::suspicious("real MIME {$mimeType}");
        }

        if (($reason = FileInspector::suspiciousContent($localPath)) !== null) {
            throw MediaRejected::suspicious($reason);
        }

        $this->checkDimensions($localPath);

        if (FileInspector::isAnimated($localPath, $mimeType)) {
            throw MediaRejected::because(MediaFailureReason::Animated, $mimeType);
        }

        $image = $this->decode($localPath);

        return new ProcessedMedia(
            mimeType: $mimeType,
            extension: $allowed[$mimeType],
            width: $image->width(),
            height: $image->height(),
            variants: $this->variants($image, $collection),
        );
    }

    /**
     * Reads the header only, so a 30000 × 30000 canvas is refused before GD allocates it.
     */
    private function checkDimensions(string $localPath): void
    {
        $size = @getimagesize($localPath);

        if ($size === false) {
            throw MediaRejected::because(MediaFailureReason::Undecodable, 'no readable header');
        }

        [$width, $height] = $size;

        if ($width > config('media.image.max_width') || $height > config('media.image.max_height')) {
            throw MediaRejected::because(MediaFailureReason::TooLarge, "{$width}x{$height}");
        }

        if ($width < config('media.image.min_width') || $height < config('media.image.min_height')) {
            throw MediaRejected::because(MediaFailureReason::TooSmall, "{$width}x{$height}");
        }
    }

    private function decode(string $localPath): ImageInterface
    {
        try {
            $image = ImageManager::gd(autoOrientation: true, strip: true)->read($localPath);
        } catch (Throwable $e) {
            throw MediaRejected::because(MediaFailureReason::Undecodable, $e->getMessage());
        }

        if ($image->isAnimated()) {
            throw MediaRejected::because(MediaFailureReason::Animated, 'multiple frames');
        }

        return $image;
    }

    /**
     * @return list<ProcessedVariant>
     */
    private function variants(ImageInterface $image, MediaCollection $collection): array
    {
        $quality = (int) config('media.image.webp_quality');
        $variants = [];

        foreach ($collection->variants() as $name => $spec) {
            $copy = clone $image;

            // Never upscale; avatars are centre-cropped squares (specs/10 §5).
            ($spec['square'] ?? false)
                ? $copy->coverDown($spec['width'], $spec['width'])
                : $copy->scaleDown(width: $spec['width']);

            $variants[] = new ProcessedVariant(
                name: VariantName::from($name),
                contents: (string) $copy->toWebp(quality: $quality),
                mimeType: 'image/webp',
                extension: 'webp',
                width: $copy->width(),
                height: $copy->height(),
            );
        }

        return $variants;
    }
}
