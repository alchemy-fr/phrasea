<?php

namespace App\Integration\Phrasea\Expose\Message;

use App\Integration\IntegrationDataManager;
use App\Integration\Phrasea\Expose\ExposeSynchronizer;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[AsMessageHandler]
final readonly class SyncBasketHandler
{
    public function __construct(
        private IntegrationDataManager $integrationDataManager,
        private ExposeSynchronizer $exposeSynchronizer,
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(SyncBasket $message): void
    {
        $integrationData = $this->integrationDataManager->getByIdTrusted($message->getId());
        try {
            $this->exposeSynchronizer->synchronize($integrationData);
        } catch (TooManyRequestsHttpException $e) {
            $delay = $e->getHeaders()['Retry-After'] ?? 300;

            $this->bus->dispatch($message, [
                new DelayStamp($delay * 1000),
            ]);
        }
    }
}
