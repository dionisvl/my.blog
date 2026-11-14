<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Media\MediaRejectedException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Turns a rejected upload into the same 422 JSON error shape the admin uses for validation failures. */
final class MediaRejectedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => 'onKernelException'];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (!$exception instanceof MediaRejectedException || !str_starts_with($event->getRequest()->getPathInfo(), '/admin/')) {
            return;
        }

        $event->setResponse(new JsonResponse(
            ['errors' => [['field' => 'image', 'message' => $exception->getMessage()]]],
            Response::HTTP_UNPROCESSABLE_ENTITY,
        ));
    }
}
