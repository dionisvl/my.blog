<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class FileNameGenerator
{
    public function generate(UploadedFile $file): string
    {
        $timestamp = new \DateTime()->format('Y-m-d_H-i-s');
        $randomString = bin2hex(random_bytes(8));
        $extension = strtolower($file->guessExtension() ?? 'png');

        return \sprintf('%s_%s.%s', $timestamp, $randomString, $extension);
    }
}
