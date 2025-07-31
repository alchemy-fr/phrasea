<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use ApiPlatform\Metadata\ApiResource;
use App\Entity\Core\Asset;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource]
class MultipleAssetOutput
{
    /**
     * @var AssetOutput[]
     */
    #[Groups([Asset::GROUP_LIST])]
    public array $assets = [];
}
