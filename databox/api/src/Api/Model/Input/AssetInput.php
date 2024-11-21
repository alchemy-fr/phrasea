<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Api\Model\Input\Attribute\AttributeInput;
use App\Entity\Core\Collection;
use App\Entity\Core\Tag;
use App\Entity\Core\Workspace;
use Symfony\Component\Validator\Constraints as Assert;

class AssetInput extends AbstractOwnerIdInput
{
    public ?string $title = null;
    public ?string $key = null;

    public ?int $privacy = null;
    public ?int $sequence = null;

    public ?string $privacyLabel = null;

    public ?string $pendingUploadToken = null;

    /**
     * @var Tag[]
     */
    public ?array $tags = null;

    /**
     * @var Workspace
     */
    #[Assert\NotNull]
    public $workspace;

    public ?Collection $collection = null;

    public ?array $destinations = null;

    /**
     * @var AttributeInput[]
     */
    public ?array $attributes = null;

    /**
     * @var AssetSourceInput|null
     */
    #[Assert\Valid]
    public $sourceFile;

    /**
     * @var string|null
     */
    public $sourceFileId;

    /**
     * @var AssetRelationshipInput|null
     */
    #[Assert\Valid]
    public $relationship;

    /**
     * @var AssetRenditionInput[]
     */
    public ?array $renditions = null;
}
