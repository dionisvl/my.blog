<?php

declare(strict_types=1);

namespace App\Media;

/**
 * Re-encodes an uploaded raster image to WebP. Decoding and re-encoding drops EXIF and anything appended to the file,
 * so only pixels reach the public storage.
 */
final readonly class ImageEncoder
{
    public const int QUALITY = 80;
    public const int MAX_SIDE = 6000;
    public const int MAX_PIXELS = 16_000_000;

    /** @throws \InvalidArgumentException when the file is not a supported, safe-to-decode still image */
    public function encode(string $path): string
    {
        $info = @getimagesize($path);

        if (false === $info) {
            throw new \InvalidArgumentException('File is not a valid image.');
        }

        [$width, $height] = $info;

        if (!\in_array($info[2], [\IMAGETYPE_JPEG, \IMAGETYPE_PNG, \IMAGETYPE_GIF, \IMAGETYPE_WEBP, \IMAGETYPE_AVIF], true)) {
            throw new \InvalidArgumentException('Image type is not allowed.');
        }

        if ($width < 1 || $height < 1 || $width > self::MAX_SIDE || $height > self::MAX_SIDE || $width * $height > self::MAX_PIXELS) {
            throw new \InvalidArgumentException(\sprintf('Image dimensions %dx%d exceed the allowed %d px per side and %d pixels.', $width, $height, self::MAX_SIDE, self::MAX_PIXELS));
        }

        $data = file_get_contents($path);

        if (false === $data) {
            throw new \RuntimeException('Cannot read uploaded image.');
        }

        $this->assertNotAnimated($data);

        set_time_limit(30);
        $image = @imagecreatefromstring($data);

        if (false === $image) {
            throw new \InvalidArgumentException('Image cannot be decoded.');
        }

        try {
            imagepalettetotruecolor($image);
            imagealphablending($image, false);
            imagesavealpha($image, true);

            ob_start();
            $ok = imagewebp($image, null, self::QUALITY);
            $encoded = (string) ob_get_clean();
        } finally {
            imagedestroy($image);
        }

        if (!$ok || '' === $encoded) {
            throw new \RuntimeException('WebP encoding failed.');
        }

        return $encoded;
    }

    private function assertNotAnimated(string $data): void
    {
        if (str_starts_with($data, 'GIF8') && substr_count($data, "\x21\xF9\x04") > 1) {
            throw new \InvalidArgumentException('Animated GIF is not supported.');
        }

        if (str_starts_with($data, 'RIFF') && 'WEBP' === substr($data, 8, 4) && (str_contains(substr($data, 12, 4096), 'ANIM') || ("VP8X" === substr($data, 12, 4) && 0 !== (\ord($data[20]) & 0x02)))) {
            throw new \InvalidArgumentException('Animated WebP is not supported.');
        }
    }
}
