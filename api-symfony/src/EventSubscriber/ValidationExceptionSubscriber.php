<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class ValidationExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        $exception = $event->getThrowable();

        if (!$exception instanceof HttpExceptionInterface) {
            return;
        }

        $previous = $exception->getPrevious();
        if (!$previous instanceof ValidationFailedException) {
            return;
        }

        $path = $request->getPathInfo();
        if (!str_starts_with($path, '/admin/posts')
            && !str_starts_with($path, '/admin/categories')
            && !str_starts_with($path, '/admin/tags')
            && !str_starts_with($path, '/admin/users')
            && !str_starts_with($path, '/admin/subscribers')
            && !str_starts_with($path, '/admin/portfolios')
            && !str_starts_with($path, '/admin/products')
            && !str_starts_with($path, '/admin/orders')
            && !str_starts_with($path, '/admin/frontparts')
        ) {
            return;
        }

        $errors = [];
        foreach ($previous->getViolations() as $violation) {
            $errors[] = [
                'field' => $violation->getPropertyPath(),
                'message' => $violation->getMessage(),
            ];
        }

        $event->setResponse(new JsonResponse([
            'errors' => $errors,
        ], $exception->getStatusCode()));
    }
}
