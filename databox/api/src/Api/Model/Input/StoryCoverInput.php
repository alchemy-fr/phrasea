<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints as Assert;

class StoryCoverInput
{
    /**
     * ID of the story item whose source file becomes the story cover.
     */
    #[Assert\NotBlank]
    public ?string $assetId = null;
}
