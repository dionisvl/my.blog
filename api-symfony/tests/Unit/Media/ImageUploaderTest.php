<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media;

use App\Media\ImageEncoder;
use App\Media\ImageUploader;
use App\Media\LocalMediaStorage;
use App\Media\MediaTarget;
use App\Media\Visibility;
use App\Service\FileValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ImageUploaderTest extends TestCase
{
    private string $root;
    private LocalMediaStorage $storage;
    private ImageUploader $uploader;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/up-'.bin2hex(random_bytes(4));
        $this->storage = new LocalMediaStorage($this->root.'/public', $this->root.'/private', '/storage');
        $this->uploader = new ImageUploader($this->storage, new FileValidator(), new ImageEncoder());
    }

    private function upload(string $path, string $name, string $mime, MediaTarget $t = MediaTarget::Post): string
    {
        return $this->uploader->upload(new UploadedFile($path, $name, $mime, null, true), $t);
    }

    public function testPngBecomesWebpAndOriginalIsKeptPrivately(): void
    {
        $src = MediaTestImages::file('png');
        $name = $this->upload($src, 'a.png', 'image/png');

        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}_[0-9a-f]{16}\.webp$/', $name);
        $public = $this->storage->get(Visibility::Public, 'uploads/'.$name);
        self::assertNotNull($public);
        self::assertSame('image/webp', (new \finfo(\FILEINFO_MIME_TYPE))->buffer($public));

        $key = 'originals/uploads/'.hash_file('sha256', $src).'.png';
        self::assertSame(file_get_contents($src), $this->storage->get(Visibility::Private, $key));
        self::assertFalse($this->storage->exists(Visibility::Public, $key));
    }

    public function testTargetsUseTheirOwnPrefix(): void
    {
        foreach ([MediaTarget::Product, MediaTarget::Portfolio] as $t) {
            $name = $this->upload(MediaTestImages::file('jpeg'), 'a.jpg', 'image/jpeg', $t);
            self::assertTrue($this->storage->exists(Visibility::Public, $t->key($name)));
        }
    }

    public function testDataAppendedAfterTheImageDoesNotReachPublicFile(): void
    {
        $payload = '<?php echo "pwned"; ?>SECRET_MARKER';
        $name = $this->upload(MediaTestImages::file('png', 40, 30, $payload), 'a.png', 'image/png');

        $public = (string) $this->storage->get(Visibility::Public, 'uploads/'.$name);
        self::assertStringNotContainsString('SECRET_MARKER', $public);
        self::assertStringNotContainsString('<?php', $public);
    }

    public function testSvgIsRejected(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'svg');
        file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->expectException(\InvalidArgumentException::class);
        $this->upload($path, 'a.svg', 'image/svg+xml');
    }

    public function testAnimatedGifIsRejected(): void
    {
        $frame = (string) file_get_contents(MediaTestImages::file('gif'));
        $gce = "\x21\xF9\x04\x00\x0A\x00\x00\x00";
        $path = (string) tempnam(sys_get_temp_dir(), 'gif');
        file_put_contents($path, substr($frame, 0, -1).$gce.$gce."\x3B");
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Animated/');
        (new ImageEncoder())->encode($path);
    }

    public function testOversizedDimensionsAreRejectedBeforeDecoding(): void
    {
        $im = imagecreatetruecolor(6500, 10);
        ob_start();
        imagepng($im);
        $path = (string) tempnam(sys_get_temp_dir(), 'big');
        file_put_contents($path, (string) ob_get_clean());
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/exceed/');
        (new ImageEncoder())->encode($path);
    }

    public function testNotAnImageIsRejectedAndNothingIsStored(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'txt');
        file_put_contents($path, 'plain text');

        try {
            $this->upload($path, 'a.png', 'image/png');
            self::fail('accepted');
        } catch (\InvalidArgumentException) {
        }

        self::assertSame([], iterator_to_array($this->storage->keys(Visibility::Public), false));
        self::assertSame([], iterator_to_array($this->storage->keys(Visibility::Private), false));
    }

    public function testDiscardRemovesOnlyThePublicFile(): void
    {
        $src = MediaTestImages::file('png');
        $name = $this->upload($src, 'a.png', 'image/png');
        $this->uploader->discard(MediaTarget::Post, $name);

        self::assertFalse($this->storage->exists(Visibility::Public, 'uploads/'.$name));
        self::assertCount(1, iterator_to_array($this->storage->keys(Visibility::Private), false));
    }
}
