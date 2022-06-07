<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

class MoveAssetInput
{
    /**
     * Collection or Workspace IRI
     */
    public ?string $destination = null;
    public ?array $ids = null;
}
