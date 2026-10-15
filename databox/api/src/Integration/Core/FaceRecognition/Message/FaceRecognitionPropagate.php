<?php

declare(strict_types=1);

namespace App\Integration\Core\FaceRecognition\Message;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

/**
 * Propagates the person of a face a user named to the similar faces of the workspace.
 */
#[MessengerMessage('p2')]
final readonly class FaceRecognitionPropagate
{
    public function __construct(
        // TODO keep integration ID to retrieve config
        private string $attributeId,
    ) {
    }

    public function getAttributeId(): string
    {
        return $this->attributeId;
    }
}
