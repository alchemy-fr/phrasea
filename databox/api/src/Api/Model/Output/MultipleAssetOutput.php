<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use ApiPlatform\Metadata\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource]
class MultipleAssetOutput
{
    /**
     * @var AssetOutput[]
     */
    #[Groups(['asset:read'])]
    public array $assets = [];
}
