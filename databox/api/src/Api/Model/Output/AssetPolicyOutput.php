<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Entity\Core\AssetPolicy\AssetPolicy;
use App\Entity\Core\Workspace;
use Symfony\Component\Serializer\Annotation\Groups;

final class AssetPolicyOutput extends AbstractUuidOutput
{
    use CreatedAtDTOTrait;

    #[Groups([AssetPolicy::GROUP_READ])]
    public ?string $name = null;

    #[Groups([AssetPolicy::GROUP_READ])]
    public ?array $users = null;

    #[Groups([AssetPolicy::GROUP_READ])]
    public ?array $groups = null;

    /**
     * @var Workspace
     */
    #[Groups([AssetPolicy::GROUP_READ])]
    public $workspace;

    #[Groups([AssetPolicy::GROUP_READ])]
    public ?array $conditions = null;

    #[Groups([AssetPolicy::GROUP_READ])]
    public ?array $actions = null;
}
