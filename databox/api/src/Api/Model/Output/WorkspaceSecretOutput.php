<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Api\Model\Output\Traits\UpdatedAtDTOTrait;
use App\Entity\Integration\WorkspaceSecret;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * A secret without its value, which the API never returns.
 */
class WorkspaceSecretOutput extends AbstractUuidOutput
{
    use CreatedAtDTOTrait;
    use UpdatedAtDTOTrait;

    #[Groups([WorkspaceSecret::GROUP_READ])]
    public ?string $name = null;

    /**
     * IRI of the workspace, null for an instance secret.
     */
    #[Groups([WorkspaceSecret::GROUP_READ])]
    public ?string $workspace = null;
}
