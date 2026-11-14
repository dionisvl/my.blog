<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media;

use App\Media\MediaStorage;
use App\Media\S3MediaStorage;
use App\Media\Visibility;
use Aws\S3\S3Client;
use PHPUnit\Framework\TestCase;

/** Runs against a real S3-compatible server (MinIO): set MEDIA_TEST_S3_ENDPOINT, otherwise skipped. */
final class S3MediaStorageTest extends TestCase
{
    use MediaStorageContractTrait;

    private ?S3MediaStorage $s3 = null;
    private S3Client $client;

    protected function setUp(): void
    {
        $endpoint = getenv('MEDIA_TEST_S3_ENDPOINT');

        if (!\is_string($endpoint) || '' === $endpoint) {
            self::markTestSkipped('MEDIA_TEST_S3_ENDPOINT is not set.');
        }

        $this->client = new S3Client([
            'version' => 'latest', 'region' => 'us-east-1', 'endpoint' => $endpoint, 'use_path_style_endpoint' => true,
            'credentials' => ['key' => (string) getenv('MEDIA_TEST_S3_KEY'), 'secret' => (string) getenv('MEDIA_TEST_S3_SECRET')],
        ]);

        foreach (['media-public', 'media-private'] as $bucket) {
            if (!$this->client->doesBucketExistV2($bucket)) {
                $this->client->createBucket(['Bucket' => $bucket]);
            }
        }

        $this->s3 = new S3MediaStorage($this->client, 'media-public', 'media-private', 'https://cdn.example.test');
    }

    protected function storage(): MediaStorage
    {
        \assert(null !== $this->s3);

        return $this->s3;
    }

    public function testSameBucketForBothAreasIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new S3MediaStorage($this->client, 'same', 'same', 'https://x');
    }

    public function testHashedWebpGetsImmutableCacheControlAndContentType(): void
    {
        $key = 'uploads/2026-11-13_10-00-00_0123456789abcdef.webp';
        $this->storage()->put(Visibility::Public, $key, MediaTestImages::file('webp'), 'image/webp');
        $head = $this->client->headObject(['Bucket' => 'media-public', 'Key' => $key]);

        self::assertSame('image/webp', $head['ContentType']);
        self::assertSame('public, max-age=31536000, immutable', $head['CacheControl']);
        $this->storage()->delete(Visibility::Public, $key);
    }

    public function testPrivateObjectIsNotInPublicBucket(): void
    {
        $key = 'originals/uploads/'.bin2hex(random_bytes(4)).'.png';
        $this->storage()->put(Visibility::Private, $key, MediaTestImages::file('png'), 'image/png');

        self::assertFalse($this->client->doesObjectExistV2('media-public', $key));
        self::assertTrue($this->client->doesObjectExistV2('media-private', $key));
        $this->storage()->delete(Visibility::Private, $key);
    }
}
