<?php

declare(strict_types=1);

namespace App\Listener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiExceptionListener implements EventSubscriberInterface
{
    const ERROR_MAP = [
        BadRequestHttpException::class => 'bad_request',
        AccessDeniedHttpException::class => 'access_denied',
    ];

    const DEFAULT_ERROR = 'internal_error';

    /**
     * @var bool
     */
    private $debug = false;

    public function __construct(bool $debug)
    {
        $this->debug = $debug;
    }

    public function onKernelException(GetResponseForExceptionEvent $event): void
    {
        $request = $event->getRequest();

        if (!(!empty($request->getAcceptableContentTypes())
            && 'application/json' === $request->getAcceptableContentTypes()[0])) {
            return;
        }

        $exception = $event->getException();
        $class = get_class($event->getException());

        if ($exception instanceof HttpException) {
            $status = $exception->getStatusCode();
            $data = [
                'error' => self::ERROR_MAP[$class] ?? self::DEFAULT_ERROR,
                'error_description' => $exception->getMessage(),
            ];
        } else {
            $status = 500;
            $data = [
                'error' => self::DEFAULT_ERROR,
                'error_description' => 'Something went wrong!',
            ];
        }

        if ($this->debug) {
            $data['debug'] = [
                'exception_class' => get_class($exception),
                'trace' => $exception->getTraceAsString(),
            ];
        }

        $response = new JsonResponse($data, $status);

        $event->setResponse($response);
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }
}
