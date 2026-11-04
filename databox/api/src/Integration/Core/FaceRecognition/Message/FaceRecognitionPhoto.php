<?php

declare(strict_types=1);

namespace App\Integration\Core\FaceRecognition\Message;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

/**
 * Gives a person (an entity of the list of the integration) the photo of its best face, when
 * it has no photo yet or an automatic one that is outdated.
 */
#[MessengerMessage('p2')]
final readonly class FaceRecognitionPhoto
{
    public function __construct(
        private string $entityId,
        private string $integrationId,
    ) {
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function getIntegrationId(): string
    {
        return $this->integrationId;
    }
}
