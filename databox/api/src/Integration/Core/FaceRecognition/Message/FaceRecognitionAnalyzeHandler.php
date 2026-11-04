<?php

declare(strict_types=1);

namespace App\Integration\Core\FaceRecognition\Message;

use App\Entity\Core\Asset;
use App\Integration\Core\FaceRecognition\FaceRecognitionAnalyzer;
use App\Integration\IntegrationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class FaceRecognitionAnalyzeHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private IntegrationManager $integrationManager,
        private FaceRecognitionAnalyzer $analyzer,
    ) {
    }

    public function __invoke(FaceRecognitionAnalyze $message): void
    {
        $asset = $this->em->find(Asset::class, $message->getAssetId());
        if (!$asset instanceof Asset) {
            return;
        }

        $workspaceIntegration = $this->integrationManager->loadIntegration($message->getIntegrationId());
        if (!$workspaceIntegration->isEnabled()) {
            return;
        }
        $config = $this->integrationManager->getIntegrationConfiguration($workspaceIntegration);

        $this->analyzer->analyze($asset, $config);
    }
}
