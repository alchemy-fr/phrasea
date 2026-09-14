<?php

declare(strict_types=1);

namespace App\Integration\Core\FaceRecognition\Message;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

/**
 * Propagates a user decision on a face (identity set, renamed or removed) to the faces of the workspace
 * that are similar to it or were derived from it.
 */
#[MessengerMessage('p2')]
final readonly class FaceRecognitionPropagate
{
    public function __construct(
        private string $faceId,
        private string $integrationId,
    ) {
    }

    public function getFaceId(): string
    {
        return $this->faceId;
    }

    public function getIntegrationId(): string
    {
        return $this->integrationId;
    }
}
