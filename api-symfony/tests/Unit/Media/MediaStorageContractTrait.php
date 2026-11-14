<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media;

use App\Media\MediaStorage;
use App\Media\Visibility;

/** Any MediaStorage driver must pass these. */
trait MediaStorageContractTrait
{
    abstract protected function storage(): MediaStorage;

    public function testPutGetExistsDelete(): void
    {
        $s = $this->storage();
        $key = 'contract/'.bin2hex(random_bytes(4)).'.png';
        $file = MediaTestImages::file('png');

        self::assertFalse($s->exists(Visibility::Public, $key));
        $s->put(Visibility::Public, $key, $file, 'image/png');
        self::assertTrue($s->exists(Visibility::Public, $key));
        self::assertSame(file_get_contents($file), $s->get(Visibility::Public, $key));
        self::assertContains($key, iterator_to_array($s->keys(Visibility::Public), false));

        $s->put(Visibility::Public, $key, $file, 'image/png');
        $s->delete(Visibility::Public, $key);
        $s->delete(Visibility::Public, $key);
        self::assertFalse($s->exists(Visibility::Public, $key));
        self::assertNull($s->get(Visibility::Public, $key));
    }

    public function testPrivateAndPublicAreSeparate(): void
    {
        $s = $this->storage();
        $key = 'originals/'.bin2hex(random_bytes(4)).'.png';
        $s->put(Visibility::Private, $key, MediaTestImages::file('png'), 'image/png');

        self::assertTrue($s->exists(Visibility::Private, $key));
        self::assertFalse($s->exists(Visibility::Public, $key));
        $s->delete(Visibility::Private, $key);
    }

    public function testUrlUsesBaseUrl(): void
    {
        self::assertStringEndsWith('/uploads/a.webp', $this->storage()->url('uploads/a.webp'));
    }

    public function testRejectsUnsafeKeys(): void
    {
        foreach (['../x.png', '/abs.png', 'a//b.png', 'a/../b.png', '', 'dir/'] as $bad) {
            try {
                $this->storage()->exists(Visibility::Public, $bad);
                self::fail('Key accepted: '.$bad);
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }
}
