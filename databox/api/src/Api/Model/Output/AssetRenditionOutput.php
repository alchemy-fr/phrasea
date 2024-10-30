<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Api\Model\Output\Traits\UpdatedAtDTOTrait;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\Share;
use Symfony\Component\Serializer\Attribute\Groups;

class AssetRenditionOutput extends AbstractUuidOutput
{
    use CreatedAtDTOTrait;
    use UpdatedAtDTOTrait;

    #[Groups([AssetRendition::GROUP_LIST, AssetRendition::GROUP_READ])]
    public $asset;

    #[Groups([AssetRendition::GROUP_LIST, AssetRendition::GROUP_READ])]
    public $definition;

    #[Groups([AssetRendition::GROUP_LIST, AssetRendition::GROUP_READ, Asset::GROUP_LIST, Asset::GROUP_READ, Share::GROUP_PUBLIC_READ])]
    public $file;

    #[Groups([AssetRendition::GROUP_LIST, AssetRendition::GROUP_READ, Share::GROUP_PUBLIC_READ])]
    public ?string $name = null;

    #[Groups([AssetRendition::GROUP_LIST, AssetRendition::GROUP_READ])]
    public ?bool $projection = null;

    #[Groups([AssetRendition::GROUP_LIST, AssetRendition::GROUP_READ])]
    public ?bool $dirty = null;

    #[Groups([AssetRendition::GROUP_LIST, AssetRendition::GROUP_READ])]
    public bool $locked = false;

    #[Groups([AssetRendition::GROUP_LIST, AssetRendition::GROUP_READ])]
    public bool $substituted = false;
}
