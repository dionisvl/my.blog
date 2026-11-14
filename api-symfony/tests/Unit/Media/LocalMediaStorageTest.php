<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media;

use App\Media\LocalMediaStorage;
use App\Media\MediaStorage;
use PHPUnit\Framework\TestCase;

final class LocalMediaStorageTest extends TestCase
{
    use MediaStorageContractTrait;

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/media-'.bin2hex(random_bytes(4));
    }

    protected function storage(): MediaStorage
    {
        return new LocalMediaStorage($this->root.'/public', $this->root.'/private', '/storage');
    }

    public function testPrivateRootInsidePublicRootIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new LocalMediaStorage($this->root.'/public', $this->root.'/public/private', '/storage');
    }

    public function testOriginalsNeverLandUnderPublicRoot(): void
    {
        $s = $this->storage();
        $s->put(\App\Media\Visibility::Private, 'originals/x.png', MediaTestImages::file('png'), 'image/png');

        self::assertFileDoesNotExist($this->root.'/public/originals/x.png');
        self::assertFileExists($this->root.'/private/originals/x.png');
    }
}
