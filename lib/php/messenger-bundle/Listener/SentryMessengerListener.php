<?php

namespace Alchemy\MessengerBundle\Listener;

use Sentry\Event;
use Sentry\EventHint;
use Sentry\ExceptionMechanism;
use Sentry\SentryBundle\EventListener\MessengerListener;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Exception\DelayedMessageHandlingException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\WrappedExceptionsInterface;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\SerializerInterface;

#[AsDecorator(decorates: MessengerListener::class)]
final readonly class SentryMessengerListener
{
    public function __construct(
        private SerializerInterface $serializer,
        private HubInterface $hub,
        private bool $captureSoftFails = true,
    ) {
    }

    public function handleWorkerMessageFailedEvent(WorkerMessageFailedEvent $event): void
    {
        if (!$this->captureSoftFails && $event->willRetry()) {
            return;
        }

        $this->hub->withScope(function (Scope $scope) use ($event): void {
            $envelope = $event->getEnvelope();
            $exception = $event->getThrowable();

            /** @var BusNameStamp|null $messageBusStamp */
            $messageBusStamp = $envelope->last(BusNameStamp::class);

            if (null !== $messageBusStamp) {
                $scope->setTag('messenger.message_bus', $messageBusStamp->getBusName());
            }

            $scope->setContext('messenger', [
                'Message' => get_debug_type($envelope->getMessage()),
                'Payload' => $this->serializer->serialize(
                    $envelope->getMessage(),
                    JsonEncoder::FORMAT, [
                        JsonEncode::OPTIONS => JSON_PRETTY_PRINT,
                    ]
                ),
                'ReceiverName' => $event->getReceiverName(),
            ]);

            $this->captureException($exception, $event->willRetry());
        });

        $this->flushClient();
    }

    /**
     * This method is called for each handled message.
     *
     * @param WorkerMessageHandledEvent $event The event
     */
    public function handleWorkerMessageHandledEvent(WorkerMessageHandledEvent $event): void
    {
        // Flush normally happens at shutdown... which only happens in the worker if it is run with a lifecycle limit
        // such as --time=X or --limit=Y. Flush immediately in a background worker.
        $this->flushClient();
    }

    /**
     * Creates Sentry events from the given exception.
     *
     * Unpacks multiple exceptions wrapped in a HandlerFailedException and notifies
     * Sentry of each individual exception.
     *
     * If the message will be retried the exceptions will be marked as handled
     * in Sentry.
     */
    private function captureException(\Throwable $exception, bool $willRetry): void
    {
        if ($exception instanceof WrappedExceptionsInterface) {
            $exception = $exception->getWrappedExceptions();
        } elseif ($exception instanceof HandlerFailedException && method_exists($exception, 'getNestedExceptions')) {
            $exception = $exception->getNestedExceptions();
        } elseif ($exception instanceof DelayedMessageHandlingException && method_exists($exception, 'getExceptions')) {
            $exception = $exception->getExceptions();
        }

        if (\is_array($exception)) {
            foreach ($exception as $nestedException) {
                $this->captureException($nestedException, $willRetry);
            }

            return;
        }

        $hint = EventHint::fromArray([
            'exception' => $exception,
            'mechanism' => new ExceptionMechanism(ExceptionMechanism::TYPE_GENERIC, $willRetry),
        ]);

        $this->hub->captureEvent(Event::createEvent(), $hint);
    }

    private function flushClient(): void
    {
        $client = $this->hub->getClient();

        if (null !== $client) {
            $client->flush();
        }
    }
}
