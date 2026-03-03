<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints as Assert;

class MoveAssetInput
{
    use IdsInputTrait;

    /**
     * Collection or Workspace IRI.
     */
    #[Assert\NotNull]
    public ?string $destination = null;
}
