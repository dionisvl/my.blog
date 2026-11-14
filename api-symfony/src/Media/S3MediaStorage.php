<?php

declare(strict_types=1);

namespace App\Media;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;

/**
 * S3-compatible driver (AWS S3, Cloudflare R2, Backblaze B2, MinIO). Public and private objects live in two buckets
 * so that a public bucket policy can never expose originals.
 */
final readonly class S3MediaStorage implements MediaStorage
{
    public function __construct(
        private S3Client $client,
        private string $publicBucket,
        private string $privateBucket,
        private string $baseUrl,
    ) {
        if ($publicBucket === $privateBucket) {
            throw new \InvalidArgumentException('Public and private buckets must differ.');
        }
    }

    public function put(Visibility $visibility, string $key, string $localPath, string $contentType): void
    {
        MediaKey::assertValid($key);

        $args = [
            'Bucket' => $this->bucket($visibility),
            'Key' => $key,
            'SourceFile' => $localPath,
            'ContentType' => $contentType,
        ];

        if (Visibility::Public === $visibility && 1 === preg_match('/_[0-9a-f]{16}\.webp$/', $key)) {
            $args['CacheControl'] = 'public, max-age=31536000, immutable';
        }

        $this->client->putObject($args);
    }

    public function get(Visibility $visibility, string $key): ?string
    {
        MediaKey::assertValid($key);

        try {
            return (string) $this->client->getObject(['Bucket' => $this->bucket($visibility), 'Key' => $key])['Body'];
        } catch (S3Exception $e) {
            if (404 === $e->getStatusCode()) {
                return null;
            }

            throw $e;
        }
    }

    public function exists(Visibility $visibility, string $key): bool
    {
        MediaKey::assertValid($key);

        return $this->client->doesObjectExistV2($this->bucket($visibility), $key);
    }

    public function delete(Visibility $visibility, string $key): void
    {
        MediaKey::assertValid($key);
        $this->client->deleteObject(['Bucket' => $this->bucket($visibility), 'Key' => $key]);
    }

    public function keys(Visibility $visibility): iterable
    {
        $pages = $this->client->getPaginator('ListObjectsV2', ['Bucket' => $this->bucket($visibility)]);

        foreach ($pages as $page) {
            foreach ($page['Contents'] ?? [] as $object) {
                yield (string) $object['Key'];
            }
        }
    }

    public function url(string $key): string
    {
        return MediaKey::url($this->baseUrl, $key);
    }

    private function bucket(Visibility $visibility): string
    {
        return Visibility::Public === $visibility ? $this->publicBucket : $this->privateBucket;
    }
}
