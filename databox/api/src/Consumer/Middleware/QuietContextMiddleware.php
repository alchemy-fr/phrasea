<?php

namespace App\Consumer\Middleware;

use Alchemy\NotifyBundle\Notification\NotifierInterface;
use Alchemy\WebhookBundle\Doctrine\Listener\EntityListener;
use App\Consumer\Stamp\QuietContextStamp;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;

final readonly class QuietContextMiddleware implements MiddlewareInterface
{
    public function __construct(
        private RequestStack $requestStack,
        private NotifierInterface $notifier,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (!$envelope->last(ConsumedByWorkerStamp::class) || !$contextStamp = $envelope->last(QuietContextStamp::class)) {
            $request = $this->requestStack->getCurrentRequest();
            if (null !== $request) {
                $envelope = $envelope->with(new QuietContextStamp(
                    $request->headers->has('X-Webhook-Disabled'),
                    $request->headers->has('X-Notification-Disabled')
                ));
            }

            return $stack->next()->handle($envelope, $stack);
        }

        $webhookEnabled = EntityListener::$enabled;
        $notificationEnabled = $this->notifier->isEnabled();

        if ($contextStamp->isNoWebhook()) {
            EntityListener::$enabled = false;
        }
        if ($contextStamp->isNoNotification()) {
            $this->notifier->setEnabled(false);
        }

        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            EntityListener::$enabled = $webhookEnabled;
            $this->notifier->setEnabled($notificationEnabled);
        }
    }
}
