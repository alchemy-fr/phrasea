<?php

declare(strict_types=1);

namespace App\Listener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiExceptionListener implements EventSubscriberInterface
{
    public const ERROR_MAP = [
        BadRequestHttpException::class => 'bad_request',
        AccessDeniedHttpException::class => 'access_denied',
    ];
    public const DEFAULT_ERROR = 'internal_error';

    private bool $debug = false;
    private LoggerInterface $logger;

    public function __construct(bool $debug, LoggerInterface $logger)
    {
        $this->debug = $debug;
        $this->logger = $logger;
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        if (!(!empty($request->getAcceptableContentTypes())
            && 'application/json' === $request->getAcceptableContentTypes()[0])) {
            return;
        }

        $exception = $event->getThrowable();
        $class = get_class($event->getThrowable());

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
            $this->logger->error($exception->getMessage());
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
