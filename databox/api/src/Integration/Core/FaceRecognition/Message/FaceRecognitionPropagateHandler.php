<?php

declare(strict_types=1);

namespace App\Integration\Core\FaceRecognition\Message;

use App\Entity\Core\Attribute;
use App\Integration\Core\FaceRecognition\FaceManager;
use App\Integration\Core\FaceRecognition\FaceRecognitionConfigResolver;
use App\Integration\IntegrationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class FaceRecognitionPropagateHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private IntegrationManager $integrationManager,
        private FaceRecognitionConfigResolver $configResolver,
        private FaceManager $faceManager,
    ) {
    }

    public function __invoke(FaceRecognitionPropagate $message): void
    {
        $face = $this->em->find(Attribute::class, $message->getAttributeId());
        if (!$face instanceof Attribute || null === $face->getEmbedding()) {
            return;
        }

        $workspaceIntegration = $this->integrationManager->loadIntegration($message->getIntegrationId());
        if (!$workspaceIntegration->isEnabled()) {
            return;
        }
        $config = $this->integrationManager->getIntegrationConfiguration($workspaceIntegration);
        if (!$config['autoPropagate'] || $this->configResolver->getDefinition($config)->getId() !== $face->getDefinition()->getId()) {
            return;
        }

        $this->faceManager->propagate($face, $config);
    }
}
