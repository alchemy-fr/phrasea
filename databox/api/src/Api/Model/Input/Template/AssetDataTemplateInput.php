<?php

declare(strict_types=1);

namespace App\Api\Model\Input\Template;

use App\Api\Model\Input\AbstractOwnerIdInput;
use App\Entity\Core\Collection;
use App\Entity\Core\Tag;
use App\Entity\Core\Workspace;
use Symfony\Component\Validator\Constraints as Assert;

class AssetDataTemplateInput extends AbstractOwnerIdInput
{
    /**
     * @Assert\NotBlank()
     */
    public ?string $name = null;

    public ?bool $public = null;
    public ?int $privacy = null;

    /**
     * @var Tag[]
     */
    public ?array $tags = null;

    /**
     * @var Workspace
     * @Assert\NotNull()
     */
    public $workspace = null;

    /**
     * @var Collection
     */
    public $collection = null;

    public bool $includeCollectionChildren = false;

    /**
     * @var TemplateAttributeInput[]
     */
    public ?array $attributes = null;
}
