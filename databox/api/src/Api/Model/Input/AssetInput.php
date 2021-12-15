<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Entity\Core\Collection;
use App\Entity\Core\Tag;
use App\Entity\Core\Workspace;

class AssetInput extends AbstractOwnerIdInput
{
    public ?string $title = null;
    public ?string $key = null;

    public ?int $privacy = null;

    public ?string $privacyLabel = null;

    /**
     * @var Tag[]
     */
    public ?array $tags = null;

    /**
     * @var Workspace
     */
    public $workspace = null;

    /**
     * @var Collection|null
     */
    public ?Collection $collection = null;

    /**
     * The source URL (must be public)
     */
    public ?string $source = null;
}
