<?php

namespace Alchemy\MessengerBundle\Middleware;

use Alchemy\MessengerBundle\Message\RetryCountSupportInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandlerArgumentsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

class RetryCountMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if ($envelope->getMessage() instanceof RetryCountSupportInterface) {
            $envelope = $envelope->with(new HandlerArgumentsStamp([
                'retryCount' => RedeliveryStamp::getRetryCountFromEnvelope($envelope),
            ]));
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
