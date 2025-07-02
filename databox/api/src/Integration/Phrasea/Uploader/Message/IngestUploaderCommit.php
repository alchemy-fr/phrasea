<?php

namespace App\Integration\Phrasea\Uploader\Message;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p2')]
final readonly class IngestUploaderCommit
{
    public function __construct(public string $integrationId, public string $commitId, public string $token)
    {
    }
}
