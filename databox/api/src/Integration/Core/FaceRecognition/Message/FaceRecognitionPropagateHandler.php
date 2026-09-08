<?php

declare(strict_types=1);

namespace App\Integration\Core\FaceRecognition\Message;

use App\Entity\Core\AssetFace;
use App\Integration\Core\FaceRecognition\FaceRecognitionAnalyzer;
use App\Integration\IntegrationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class FaceRecognitionPropagateHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private IntegrationManager $integrationManager,
        private FaceRecognitionAnalyzer $analyzer,
    ) {
    }

    public function __invoke(FaceRecognitionPropagate $message): void
    {
        $face = $this->em->find(AssetFace::class, $message->getFaceId());
        if (!$face instanceof AssetFace) {
            return;
        }

        $workspaceIntegration = $this->integrationManager->loadIntegration($message->getIntegrationId());
        if (!$workspaceIntegration->isEnabled()) {
            return;
        }
        $config = $this->integrationManager->getIntegrationConfiguration($workspaceIntegration);

        if ($face->isUserIdentified()) {
            $this->analyzer->propagateIdentity($face, $config);
        } else {
            $this->analyzer->revokeIdentity($face, $config);
        }
    }
}
