<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Throwable;

abstract class AbstractRetryableHandler extends AbstractEntityManagerHandler
{
    protected static int $retryCount = 2;

    const RETRY_KEY = '_retry';
    protected EventProducer $eventProducer;

    /**
     * @required
     */
    public function setEventProducer(EventProducer $eventProducer): void
    {
        $this->eventProducer = $eventProducer;
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();

        try {
            $this->doHandle($payload);
        } catch (Throwable $e) {
            if ($this->isRetryableException($e)) {
                $retry = $payload[self::RETRY_KEY] ?? [];
                $retryCount = ($retry['count'] ?? 0) + 1;

                if ($retryCount <= static::$retryCount) {
                    $this->logger->error(sprintf(
                        'Retrying event: %s (%s): %s: %s',
                        $message->getType(),
                        json_encode($payload),
                        get_class($e),
                        $e->getMessage()
                    ));

                    $this->eventProducer->publish(new EventMessage(
                        $message->getType(),
                        array_merge($payload, [
                            self::RETRY_KEY => [
                                'count' => $retryCount,
                                'exception' => [
                                    'class' => get_class($e),
                                    'message' => $e->getMessage(),
                                ],
                            ],
                        ])
                    ));

                    return;
                }
            }

            throw $e;
        }
    }

    abstract protected function doHandle(array $payload): void;

    abstract protected function isRetryableException(Throwable $e): bool;
}
