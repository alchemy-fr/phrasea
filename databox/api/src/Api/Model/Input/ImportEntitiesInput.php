<?php

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class ImportEntitiesInput
{
    #[Assert\NotBlank]
    public ?string $data = null;

    #[Assert\NotBlank]
    public ?string $format = null;
}
