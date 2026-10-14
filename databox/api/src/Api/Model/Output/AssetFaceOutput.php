<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Api\Model\Output\Traits\UpdatedAtDTOTrait;
use App\Entity\Core\AssetFace;
use Symfony\Component\Serializer\Attribute\Groups;

class AssetFaceOutput extends AbstractUuidOutput
{
    use CreatedAtDTOTrait;
    use UpdatedAtDTOTrait;

    #[Groups([AssetFace::GROUP_LIST])]
    public $asset;

    /**
     * Bounding box normalized to the image size: {x, y, w, h} in [0, 1], origin top-left.
     */
    #[Groups([AssetFace::GROUP_LIST])]
    public array $box = [];

    #[Groups([AssetFace::GROUP_LIST])]
    public int $position = 0;

    #[Groups([AssetFace::GROUP_LIST])]
    public float $confidence = 0.0;

    #[Groups([AssetFace::GROUP_LIST])]
    public ?string $identity = null;

    #[Groups([AssetFace::GROUP_LIST])]
    public ?float $identityConfidence = null;

    /**
     * "user" when set by a user, "auto" when recognized from a user-identified face.
     */
    #[Groups([AssetFace::GROUP_LIST])]
    public ?string $identityOrigin = null;

    /**
     * ID of the user-identified face an automatic identity was derived from.
     */
    #[Groups([AssetFace::GROUP_LIST])]
    public ?string $reference = null;

    #[Groups([AssetFace::GROUP_LIST])]
    public ?int $age = null;

    #[Groups([AssetFace::GROUP_LIST])]
    public ?string $gender = null;
}
