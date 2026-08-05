<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Entity\Core\Asset;
use Symfony\Component\Serializer\Annotation\Groups;

class AttributeFilterRuleInput
{
    /**
     * @var string[]|null
     */
    #[Groups([Asset::GROUP_WRITE])]
    public ?array $userIds = null;

    /**
     * @var string[]|null
     */
    #[Groups([Asset::GROUP_WRITE])]
    public ?array $groupIds = null;

    #[Groups([Asset::GROUP_WRITE])]
    public ?string $workspaceId = null;

    #[Groups([Asset::GROUP_WRITE])]
    public ?string $condition = null;
}
