<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Api\Model\Output\Traits\ExtraMetadataDTOTrait;

class CopyAssetInput extends AbstractOwnerIdInput
{
    use ExtraMetadataDTOTrait;

    /**
     * Collection or Workspace IRI.
     */
    public ?string $destination = null;
    public ?array $ids = null;
    public bool $byReference = false;
    public bool $withAttributes = false;
    public bool $withTags = false;
}
