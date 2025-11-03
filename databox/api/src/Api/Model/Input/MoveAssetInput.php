<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

class MoveAssetInput
{
    use IdsInputTrait;

    /**
     * Collection or Workspace IRI.
     */
    public ?string $destination = null;
}
