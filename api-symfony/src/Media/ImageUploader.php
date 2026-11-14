<?php

declare(strict_types=1);

namespace App\Media;

use App\Service\FileValidator;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * The single entry point for storing an uploaded image for any entity (post, product, portfolio).
 * Public result: a WebP at quality 80. The unmodified original goes to the private area and is never served.
 */
final readonly class ImageUploader
{
    public function __construct(
        private MediaStorage $storage,
        private FileValidator $validator,
        private ImageEncoder $encoder,
    ) {
    }

    /** @return string file name to store in the entity (the key is {@see MediaTarget::key()} of it) */
    public function upload(UploadedFile $file, MediaTarget $target): string
    {
        $this->validator->validate($file);

        $webp = $this->encoder->encode($file->getPathname());
        $hash = hash('sha256', $webp);
        $filename = \sprintf('%s_%s.webp', (new \DateTimeImmutable())->format('Y-m-d_H-i-s'), substr($hash, 0, 16));

        $tmp = tempnam(sys_get_temp_dir(), 'media');

        if (false === $tmp) {
            throw new \RuntimeException('Cannot create a temporary file.');
        }

        try {
            if (false === file_put_contents($tmp, $webp)) {
                throw new \RuntimeException('Cannot write a temporary file.');
            }

            $originalHash = hash_file('sha256', $file->getPathname());
            $originalExt = strtolower($file->guessExtension() ?? 'bin');
            $originalKey = \sprintf('originals/%s/%s.%s', $target->value, $originalHash, $originalExt);
            $this->storage->put(Visibility::Private, $originalKey, $file->getPathname(), (string) $file->getMimeType());

            $this->storage->put(Visibility::Public, $target->key($filename), $tmp, 'image/webp');
        } finally {
            @unlink($tmp);
        }

        return $filename;
    }

    public function discard(MediaTarget $target, ?string $filename): void
    {
        if (null !== $filename && '' !== $filename) {
            $this->storage->delete(Visibility::Public, $target->key($filename));
        }
    }
}
