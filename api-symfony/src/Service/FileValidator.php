<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class FileValidator
{
    private const array ALLOWED_MIME_TYPES = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'image/gif' => ['gif'],
        'image/avif' => ['avif'],
        'image/svg+xml' => ['svg'],
        'image/x-icon' => ['ico'],
        'image/bmp' => ['bmp'],
        'image/tiff' => ['tiff', 'tif'],
    ];

    private const int MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB

    public function validate(UploadedFile $file): void
    {
        $this->validateFileExists($file);
        $this->validateMimeType($file);
        $this->validateSize($file);
        $this->validateContent($file);
    }

    private function validateFileExists(UploadedFile $file): void
    {
        $tmpPath = $file->getRealPath();

        if (false === $tmpPath || !file_exists($tmpPath)) {
            throw new \RuntimeException(\sprintf(
                'Uploaded file not found. Real path: %s, Exists: %s',
                $tmpPath ?? 'NULL',
                file_exists($tmpPath ?? '') ? 'yes' : 'no'
            ));
        }
    }

    private function validateMimeType(UploadedFile $file): void
    {
        try {
            $mimeType = $file->getMimeType();
        } catch (\Exception) {
            throw new \InvalidArgumentException('Cannot determine file MIME type. File may be corrupted or not properly uploaded.');
        }

        if (!$this->isAllowedMimeType($mimeType)) {
            throw new \InvalidArgumentException(\sprintf('File MIME type "%s" is not allowed.', $mimeType));
        }
    }

    private function isAllowedMimeType(?string $mimeType): bool
    {
        if (null === $mimeType) {
            return false;
        }

        return isset(self::ALLOWED_MIME_TYPES[$mimeType]);
    }

    private function validateSize(UploadedFile $file): void
    {
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException(
                \sprintf('File size %d exceeds maximum allowed size %d.', $file->getSize(), self::MAX_FILE_SIZE)
            );
        }
    }

    private function validateContent(UploadedFile $file): void
    {
        $extension = strtolower($file->guessExtension() ?? '');

        try {
            $mimeType = $file->getMimeType();
        } catch (\Exception) {
            throw new \InvalidArgumentException('Cannot determine file MIME type. File may be corrupted or not properly uploaded.');
        }

        if (!isset(self::ALLOWED_MIME_TYPES[$mimeType])) {
            throw new \InvalidArgumentException('MIME type validation failed.');
        }

        $allowedExtensions = self::ALLOWED_MIME_TYPES[$mimeType];

        if (!\in_array($extension, $allowedExtensions, true)) {
            throw new \InvalidArgumentException(
                \sprintf('File extension ".%s" does not match MIME type "%s".', $extension, $mimeType)
            );
        }

        $this->validateImageContent($file);
    }

    private function validateImageContent(UploadedFile $file): void
    {
        $tmpPath = $file->getRealPath();

        if (false === $tmpPath) {
            throw new \RuntimeException('Cannot get real path of uploaded file.');
        }

        if (!file_exists($tmpPath)) {
            throw new \RuntimeException(\sprintf('Uploaded file does not exist at path: %s', $tmpPath));
        }

        $imageInfo = @getimagesize($tmpPath);

        if (false === $imageInfo) {
            throw new \InvalidArgumentException('File is not a valid image.');
        }

        $mimeType = $imageInfo['mime'];

        if (!$this->isAllowedMimeType($mimeType)) {
            throw new \InvalidArgumentException(\sprintf('Image MIME type "%s" is not allowed.', $mimeType));
        }
    }
}
