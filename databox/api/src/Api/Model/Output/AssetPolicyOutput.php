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

    #[Groups([AssetPolicy::GROUP_LIST])]
    public ?string $name = null;

    #[Groups([AssetPolicy::GROUP_LIST])]
    public ?bool $enabled = null;

    #[Groups([AssetPolicy::GROUP_LIST])]
    public ?array $users = null;

    #[Groups([AssetPolicy::GROUP_LIST])]
    public ?array $groups = null;

    /**
     * @var Workspace
     */
    #[Groups([AssetPolicy::GROUP_LIST])]
    public $workspace;

    #[Groups([AssetPolicy::GROUP_LIST])]
    public $owner;

    #[Groups([AssetPolicy::GROUP_LIST])]
    public ?array $conditions = null;

    #[Groups([AssetPolicy::GROUP_LIST])]
    public ?array $actions = null;
}
