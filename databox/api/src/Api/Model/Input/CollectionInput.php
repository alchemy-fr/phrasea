<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Entity\Core\Collection;
use App\Entity\Core\Tag;
use App\Entity\Core\Workspace;
use Symfony\Component\Serializer\Annotation\Groups;

class CollectionInput
{
    /**
     * @Groups({"asset:write"})
     */
    public ?string $title = null;

    /**
     * @Groups({"asset:write"})
     */
    public ?int $privacy = null;

    /**
     * @Groups({"asset:write"})
     */
    public ?string $privacyLabel = null;

    /**
     * @var Tag[]
     * @Groups({"asset:write"})
     */
    public ?array $tags = null;

    /**
     * @var Workspace
     * @Groups({"asset:write"})
     */
    public $workspace = null;

    /**
     * @var Collection|null
     * @Groups({"asset:write"})
     */
    public ?Collection $parent = null;
}
