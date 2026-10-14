<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Api\Model\Input\Attribute\AttributeInput;
use App\Api\Model\Output\Traits\ExtraMetadataDTOTrait;
use App\Entity\Core\Collection;
use App\Entity\Core\Tag;
use App\Entity\Core\Workspace;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class AssetInput extends AbstractOwnerIdInput
{
    use UploadInputTrait;
    use ExtraMetadataDTOTrait;

    #[Assert\Length(max: 255)]
    public ?string $name = null;
    public ?string $externalId = null;
    public ?string $trackingId = null;
    public ?string $key = null;

    public ?int $privacy = null;
    public ?int $sequence = null;

    public ?string $privacyLabel = null;

    /**
     * @var Tag[]
     */
    public ?array $tags = null;

    /**
     * @var Workspace
     */
    public $workspace;

    public ?Collection $collection = null;

    public ?array $destinations = null;

    /**
     * @var AttributeInput[]
     */
    public ?array $attributes = null;

    /**
     * @var AssetRelationshipInput|null
     */
    #[Assert\Valid]
    public $relationship;

    /**
     * @var AssetRenditionInput[]
     */
    public ?array $renditions = null;

    #[Groups(['_'])]
    public ?array $relationExtraMetadata = null;

    public ?bool $isStory = null;

    public ?AssetStoryInput $story = null;

    /**
     * Story creation only: IDs of existing assets added to the story, in this order.
     *
     * @var string[]|null
     */
    #[Assert\All([new Assert\Type('string'), new Assert\NotBlank()])]
    public ?array $storyAssets = null;

    /**
     * Story creation only: ID of the asset (one of storyAssets) whose source file becomes the story cover.
     */
    public ?string $storyCover = null;
}
