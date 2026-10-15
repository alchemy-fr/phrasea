<?php

declare(strict_types=1);

namespace App\Integration\Core\FaceRecognition\Message;

use App\Entity\Core\Attribute;
use App\Integration\Core\FaceRecognition\FaceManager;
use App\Integration\Core\FaceRecognition\FaceRecognitionConfigResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class FaceRecognitionPropagateHandler
{
    public function __construct(
        private EntityManagerInterface $em,
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

        $config = $this->configResolver->getConfigOfDefinition($face->getDefinition());
        if (null === $config || !$config['autoPropagate']) {
            return;
        }

        $this->faceManager->propagate($face, $config);
    }
}
