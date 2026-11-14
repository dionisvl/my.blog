<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media;

use App\EventSubscriber\MediaRejectedSubscriber;
use App\Media\ImageEncoder;
use App\Media\ImageUploader;
use App\Media\LocalMediaStorage;
use App\Media\MediaRejectedException;
use App\Media\MediaTarget;
use App\Service\FileValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class MediaRejectedTest extends TestCase
{
    public function testUploaderReportsRejectionsAsMediaRejected(): void
    {
        $root = sys_get_temp_dir().'/rej-'.bin2hex(random_bytes(4));
        $uploader = new ImageUploader(new LocalMediaStorage($root.'/p', $root.'/q', '/storage'), new FileValidator(), new ImageEncoder());
        $path = (string) tempnam(sys_get_temp_dir(), 'svg');
        file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg"/>');

        $this->expectException(MediaRejectedException::class);
        $uploader->upload(new UploadedFile($path, 'a.svg', 'image/svg+xml', null, true), MediaTarget::Post);
    }

    public function testSubscriberAnswers422OnlyForAdminPaths(): void
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $subscriber = new MediaRejectedSubscriber();

        $admin = new ExceptionEvent($kernel, Request::create('/admin/posts/store', 'POST'), HttpKernelInterface::MAIN_REQUEST, new MediaRejectedException('Image type is not allowed.'));
        $subscriber->onKernelException($admin);
        self::assertSame(422, $admin->getResponse()?->getStatusCode());
        self::assertStringContainsString('Image type is not allowed.', (string) $admin->getResponse()?->getContent());

        $other = new ExceptionEvent($kernel, Request::create('/api/x', 'POST'), HttpKernelInterface::MAIN_REQUEST, new MediaRejectedException('x'));
        $subscriber->onKernelException($other);
        self::assertNull($other->getResponse());
    }
}
