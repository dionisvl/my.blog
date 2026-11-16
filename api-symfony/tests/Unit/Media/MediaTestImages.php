<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media;

final class MediaTestImages
{
    public static function file(string $format, int $w = 40, int $h = 30, string $suffix = ''): string
    {
        $im = imagecreatetruecolor($w, $h);

        if (false === $im) {
            throw new \RuntimeException('Failed to create image.');
        }

        imagefilledrectangle($im, 0, 0, $w, $h, imagecolorallocate($im, 200, 40, 40));
        ob_start();
        match ($format) {
            'png' => imagepng($im),
            'jpeg' => imagejpeg($im),
            'gif' => imagegif($im),
            'webp' => imagewebp($im),
            default => throw new \InvalidArgumentException('Unsupported test image format: '.$format),
        };
        $data = (string) ob_get_clean().$suffix;
        $path = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents((string) $path, $data);

        return (string) $path;
    }
}
