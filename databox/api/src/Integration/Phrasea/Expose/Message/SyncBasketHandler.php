<?php

namespace App\Integration\Phrasea\Expose\Message;

use App\Integration\IntegrationDataManager;
use App\Integration\Phrasea\Expose\ExposeSynchronizer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SyncBasketHandler
{
    public function __construct(
        private IntegrationDataManager $integrationDataManager,
        private ExposeSynchronizer $exposeSynchronizer,
    ) {
    }

    public function __invoke(SyncBasket $message): void
    {
        $integrationData = $this->integrationDataManager->getByIdTrusted($message->getId());
        $this->exposeSynchronizer->synchronize($integrationData);
    }
}
