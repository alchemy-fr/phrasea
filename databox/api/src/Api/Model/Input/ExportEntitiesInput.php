<?php

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class ExportEntitiesInput
{
    public ?string $locale = null;
    #[Assert\NotBlank]
    public ?string $format = null;

}
