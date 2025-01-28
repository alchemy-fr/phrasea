<?php

namespace Alchemy\CoreBundle\Listener;

use Alchemy\CoreBundle\Exception\BodyHttpClientException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;

#[AsEventListener(KernelEvents::EXCEPTION, method: 'onException')]
final readonly class ClientExceptionListener
{
    public function onException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$exception instanceof ClientExceptionInterface || $exception instanceof BodyHttpClientException) {
            return;
        }

        $event->setThrowable(new BodyHttpClientException($exception));
    }

    public function wrapClientRequest(callable $handler): mixed
    {
        try {
            return $handler();
        } catch (ClientExceptionInterface $e) {
            throw new BodyHttpClientException($e);
        }
    }
}
