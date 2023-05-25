<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Entity\Core\Tag;
use Symfony\Component\Serializer\Annotation\Groups;

class TagFilterRuleInput
{
    /**
     * @Groups({"asset:write"})
     */
    public ?string $userId = null;

    /**
     * @Groups({"asset:write"})
     */
    public ?string $groupId = null;

    /**
     * @Groups({"asset:write"})
     */
    public ?string $workspaceId = null;

    /**
     * @Groups({"asset:write"})
     */
    public ?string $collectionId = null;

    /**
     * @var Tag[]
     *
     * @Groups({"asset:write"})
     */
    public ?array $include = null;

    /**
     * @var Tag[]
     *
     * @Groups({"asset:write"})
     */
    public ?array $exclude = null;
}
