<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use Symfony\Component\Serializer\Annotation\Groups;

class MultipleAssetOutput
{
    /**
     * @var AssetOutput[]
     * @Groups({"asset:read"})
     */
    public array $assets = [];
}
