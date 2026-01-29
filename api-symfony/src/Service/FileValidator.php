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
    ];

    private const int MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB

    public function validate(UploadedFile $file): void
    {
        $this->validateMimeType($file);
        $this->validateSize($file);
        $this->validateContent($file);
    }

    private function validateMimeType(UploadedFile $file): void
    {
        $mimeType = $file->getMimeType();

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
        $mimeType = $file->getMimeType();

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

        if (false === $tmpPath || !file_exists($tmpPath)) {
            throw new \RuntimeException('Cannot read uploaded file.');
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
