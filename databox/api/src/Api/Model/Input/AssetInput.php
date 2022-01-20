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

    public bool $sourceIsPrivate = false;

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

    // TODO implement

    public ?bool $copySource = null;

    /**
     * Alternative URLs.
     *
     * If path is not accessible publicly, "download" and "open" should be provided with public URI.
     *
     * @var AlternateUrlInput[]
     */
    public ?array $alternateUrls = null;
}
