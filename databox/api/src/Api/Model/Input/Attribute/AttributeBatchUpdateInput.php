<?php

declare(strict_types=1);

namespace App\Api\Model\Input\Attribute;

use App\Api\Model\Input\InputLimits;
use Symfony\Component\Validator\Constraints as Assert;

class AttributeBatchUpdateInput extends AssetAttributeBatchUpdateInput
{
    /**
     * Asset IDs.
     *
     * @var string[]
     */
    #[Assert\Count(max: InputLimits::MAX_IDS)]
    public ?array $assets = null;

    #[Assert\NotNull]
    public ?string $workspaceId = null;

    protected function getBatchAssetIds(): ?array
    {
        return $this->assets;
    }
}
