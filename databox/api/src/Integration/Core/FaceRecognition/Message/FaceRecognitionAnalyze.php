<?php

declare(strict_types=1);

namespace App\Integration\Core\FaceRecognition\Message;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p2')]
final readonly class FaceRecognitionAnalyze
{
    public function __construct(
        private string $assetId,
        private string $integrationId,
    ) {
    }

    public function getAssetId(): string
    {
        return $this->assetId;
    }

    public function getIntegrationId(): string
    {
        return $this->integrationId;
    }
}
