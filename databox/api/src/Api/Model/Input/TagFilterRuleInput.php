<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Entity\Core\Asset;
use App\Entity\Core\Tag;
use Symfony\Component\Serializer\Annotation\Groups;

class TagFilterRuleInput
{
    #[Groups([Asset::GROUP_WRITE])]
    public ?string $userId = null;

    #[Groups([Asset::GROUP_WRITE])]
    public ?string $groupId = null;

    #[Groups([Asset::GROUP_WRITE])]
    public ?string $workspaceId = null;

    #[Groups([Asset::GROUP_WRITE])]
    public ?string $collectionId = null;

    /**
     * @var Tag[]
     */
    #[Groups([Asset::GROUP_WRITE])]
    public ?array $include = null;

    /**
     * @var Tag[]
     */
    #[Groups([Asset::GROUP_WRITE])]
    public ?array $exclude = null;
}
