<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;
use App\Entity\Asset;
use App\Entity\SubDefinition;

/**
 * Checks the file of an asset or a sub-definition sent to a presigned PUT URL,
 * once the URL expired.
 */
#[MessengerMessage('p1')]
final readonly class CheckPresignedUpload
{
    /**
     * @param class-string<Asset|SubDefinition> $entityClass
     */
    public function __construct(
        public string $entityClass,
        public string $id,
    ) {
    }
}
