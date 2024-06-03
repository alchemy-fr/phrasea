<?php

namespace App\Integration\Phrasea\Expose\Message;

use App\Entity\Integration\IntegrationBasketData;
use App\Integration\IntegrationDataManager;
use App\Integration\Phrasea\Expose\ExposeSynchronizer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SyncBasketHandler
{
    public function __construct(
        private IntegrationDataManager $integrationDataManager,
        private ExposeSynchronizer $exposeSynchronizer,
    )
    {
    }

    public function __invoke(SyncBasket $message): void
    {
        /** @var IntegrationBasketData $integrationData */
        $integrationData = $this->integrationDataManager->getByIdTrusted(IntegrationBasketData::class, $message->getId());

        $this->exposeSynchronizer->synchronize($integrationData);
    }
}
