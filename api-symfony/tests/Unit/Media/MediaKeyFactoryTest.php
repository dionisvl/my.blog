<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media;

use App\Media\ImageEncoder;
use App\Media\MediaKey;
use App\Media\MediaStorageFactory;
use PHPUnit\Framework\TestCase;

final class MediaKeyFactoryTest extends TestCase
{
    public function testUrlEncodesLegacyNamesWithoutThrowing(): void
    {
        self::assertSame('/storage/uploads/my%20photo%20%281%29.jpg', MediaKey::url('/storage', 'uploads/my photo (1).jpg'));
        self::assertSame('https://cdn.test/uploads/%D1%84%D0%BE%D1%82%D0%BE.png', MediaKey::url('https://cdn.test/', 'uploads/фото.png'));
    }

    public function testS3RequiresAbsoluteHttpsBaseUrl(): void
    {
        $factory = new MediaStorageFactory('/tmp', 's3', '/storage', 'var/p', 'http://s3.test', 'auto', 'pub', 'priv', 'k', 's');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/https/');
        $factory->create();
    }

    public function testS3AcceptsSitePathButNotTheLocalStoragePath(): void
    {
        $ok = new MediaStorageFactory('/tmp', 's3', '/media', 'var/p', 'http://s3.test', 'auto', 'pub', 'priv', 'k', 's');
        self::assertSame('/media/uploads/a.webp', $ok->create()->url('uploads/a.webp'));

        $this->expectException(\InvalidArgumentException::class);
        (new MediaStorageFactory('/tmp', 's3', '/storage/', 'var/p', 'http://s3.test', 'auto', 'pub', 'priv', 'k', 's'))->create();
    }

    public function testUnknownDriverAndMissingS3SettingsAreRefused(): void
    {
        foreach ([['nope', 'https://x'], ['s3', 'https://x']] as [$driver, $base]) {
            try {
                (new MediaStorageFactory('/tmp', $driver, $base, 'var/p', '', 'auto', '', '', '', ''))->create();
                self::fail('accepted '.$driver);
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testNonImageTypesAreRejectedByRealType(): void
    {
        $bmp = (string) tempnam(sys_get_temp_dir(), 'bmp');
        $im = imagecreatetruecolor(4, 4);
        imagebmp($im, $bmp);
        $this->expectException(\InvalidArgumentException::class);
        (new ImageEncoder())->encode($bmp);
    }
}
