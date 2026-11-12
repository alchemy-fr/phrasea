<?php

namespace App\Consumer\Handler;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

/**
 * Builds the archive of a publication if it is not up to date, then sends its link by
 * email when a download request is given.
 */
#[MessengerMessage('p1')]
final readonly class PublicationArchiveRequest
{
    public function __construct(
        private string $publicationId,
        private ?string $downloadRequestId = null,
    ) {
    }

    public function getPublicationId(): string
    {
        return $this->publicationId;
    }

    public function getDownloadRequestId(): ?string
    {
        return $this->downloadRequestId;
    }
}
