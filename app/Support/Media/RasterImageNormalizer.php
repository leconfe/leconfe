<?php

namespace App\Support\Media;

class RasterImageNormalizer
{
    /**
     * Reject files whose decoded pixel count exceeds this limit before decoding.
     */
    public const MAX_PIXELS = 40_000_000;

    /**
     * @return array<int, string>
     */
    public static function allowedMimeTypes(): array
    {
        return (array) config('media-library.raster_image_mime_types', []);
    }

    public static function isRasterImage(?string $mimeType): bool
    {
        return $mimeType !== null && in_array($mimeType, static::allowedMimeTypes(), true);
    }

    public static function isImage(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/');
    }

    /**
     * Decode and re-encode raster image bytes. Re-encoding drops embedded metadata
     * and guarantees the stored bytes are a valid raster image. Returns null when
     * the bytes are not a decodable raster image or exceed the pixel limit.
     */
    public static function normalize(string $mimeType, string $contents): ?string
    {
        if (! static::isRasterImage($mimeType)) {
            return null;
        }

        $size = @getimagesizefromstring($contents);

        if (! is_array($size)) {
            return null;
        }

        [$width, $height] = $size;

        if ($width < 1 || $height < 1 || ($width * $height) > static::MAX_PIXELS) {
            return null;
        }

        $image = @imagecreatefromstring($contents);

        if ($image === false) {
            return null;
        }

        try {
            imagepalettetotruecolor($image);
            imagealphablending($image, false);
            imagesavealpha($image, true);

            ob_start();
            $encoded = match ($mimeType) {
                'image/jpeg' => imagejpeg($image, null, 90),
                'image/png' => imagepng($image),
                'image/webp' => imagewebp($image, null, 90),
                default => false,
            };
            $normalized = ob_get_clean();

            if ($encoded === false || ! is_string($normalized) || $normalized === '') {
                return null;
            }

            return $normalized;
        } finally {
            imagedestroy($image);
        }
    }
}
