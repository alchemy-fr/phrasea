<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Entity\Core\Collection;
use App\Entity\Core\Tag;
use App\Entity\Core\Workspace;

class CollectionInput extends AbstractOwnerIdInput
{
    public ?string $title = null;

    public ?int $privacy = null;

    public ?string $privacyLabel = null;

    public ?string $key = null;

    /**
     * @var Tag[]
     */
    public ?array $tags = null;

    /**
     * @var Workspace
     */
    public $workspace = null;

    public ?Collection $parent = null;
}
