<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Api\Model\Output\Traits\UpdatedAtDTOTrait;
use App\Entity\Integration\WorkspaceEnv;
use Symfony\Component\Serializer\Attribute\Groups;

class WorkspaceEnvOutput extends AbstractUuidOutput
{
    use CreatedAtDTOTrait;
    use UpdatedAtDTOTrait;

    #[Groups([WorkspaceEnv::GROUP_READ])]
    public ?string $name = null;

    #[Groups([WorkspaceEnv::GROUP_READ])]
    public ?string $value = null;

    /**
     * IRI of the workspace, null for an instance variable.
     */
    #[Groups([WorkspaceEnv::GROUP_READ])]
    public ?string $workspace = null;
}
