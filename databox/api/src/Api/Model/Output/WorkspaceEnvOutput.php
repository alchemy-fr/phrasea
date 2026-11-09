<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use App\Entity\Integration\WorkspaceEnv;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * A variable, by its name only: its value may hold sensitive data.
 */
class WorkspaceEnvOutput extends AbstractUuidOutput
{
    #[Groups([WorkspaceEnv::GROUP_READ])]
    public ?string $name = null;
}
