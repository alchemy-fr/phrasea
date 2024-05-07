<?php

namespace App\Integration\Aws\Rekognition\Message;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;
use App\Integration\Message\AbstractFileActionMessage;

#[MessengerMessage('p2')]
final readonly class RekognitionAnalyze extends AbstractFileActionMessage
{
    public function __construct(
        string $fileId,
        string $integrationId,
        private string $category,
    )
    {
        parent::__construct($fileId, $integrationId);
    }

    public function getCategory(): string
    {
        return $this->category;
    }
}
