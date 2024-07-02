<?php

declare(strict_types=1);

namespace App\Api\Model\Input\Attribute;

use Symfony\Component\Validator\Constraints\NotNull;

class AttributeBatchUpdateInput extends AssetAttributeBatchUpdateInput
{
    /**
     * Asset IDs.
     *
     * @var string[]
     */
    public ?array $assets = null;

    #[NotNull]
    public ?string $workspaceId = null;
}
