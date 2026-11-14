<?php

declare(strict_types=1);

namespace App\Media;

use Aws\S3\S3Client;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class MediaStorageFactory
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
        #[Autowire('%env(MEDIA_DRIVER)%')] private string $driver,
        #[Autowire('%env(MEDIA_BASE_URL)%')] private string $baseUrl,
        #[Autowire('%env(MEDIA_PRIVATE_DIR)%')] private string $privateDir,
        #[Autowire('%env(MEDIA_S3_ENDPOINT)%')] private string $s3Endpoint,
        #[Autowire('%env(MEDIA_S3_REGION)%')] private string $s3Region,
        #[Autowire('%env(MEDIA_S3_PUBLIC_BUCKET)%')] private string $s3PublicBucket,
        #[Autowire('%env(MEDIA_S3_PRIVATE_BUCKET)%')] private string $s3PrivateBucket,
        #[Autowire('%env(MEDIA_S3_KEY)%')] private string $s3Key,
        #[Autowire('%env(MEDIA_S3_SECRET)%')] private string $s3Secret,
    ) {
    }

    public function create(): MediaStorage
    {
        return match ($this->driver) {
            'local' => $this->local(),
            's3' => $this->requireHttpsBaseUrl()->s3(),
            default => throw new \InvalidArgumentException(\sprintf('Unknown MEDIA_DRIVER "%s" (use local or s3).', $this->driver)),
        };
    }

    private function requireHttpsBaseUrl(): self
    {
        $base = rtrim($this->baseUrl, '/');

        if ('/storage' === $base || !(str_starts_with($base, 'https://') || str_starts_with($base, '/'))) {
            throw new \InvalidArgumentException('With MEDIA_DRIVER=s3, MEDIA_BASE_URL must be an https:// URL of the public bucket/CDN or a site path such as /media (not /storage).');
        }

        return $this;
    }

    public function local(): LocalMediaStorage
    {
        $private = str_starts_with($this->privateDir, '/') ? $this->privateDir : $this->projectDir.'/'.$this->privateDir;

        return new LocalMediaStorage($this->projectDir.'/public/storage', $private, $this->baseUrl);
    }

    public function s3(): S3MediaStorage
    {
        foreach (['endpoint' => $this->s3Endpoint, 'public bucket' => $this->s3PublicBucket, 'private bucket' => $this->s3PrivateBucket, 'key' => $this->s3Key, 'secret' => $this->s3Secret] as $name => $value) {
            if ('' === $value) {
                throw new \InvalidArgumentException(\sprintf('MEDIA_S3 %s is not configured.', $name));
            }
        }

        $client = new S3Client([
            'version' => 'latest',
            'region' => '' === $this->s3Region ? 'auto' : $this->s3Region,
            'endpoint' => $this->s3Endpoint,
            'use_path_style_endpoint' => true,
            'credentials' => ['key' => $this->s3Key, 'secret' => $this->s3Secret],
        ]);

        return new S3MediaStorage($client, $this->s3PublicBucket, $this->s3PrivateBucket, $this->baseUrl);
    }
}
