<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints as Assert;

class AssetRelationshipInput
{
    /**
     * Asset source ID.
     */
    public ?string $source = null;
    /**
     * Workspace integration ID.
     */
    public ?string $integration = null;

    public ?string $sourceFileId = null;

    /**
     * Type of relationship.
     */
    #[Assert\NotBlank]
    public ?string $type = null;
}
