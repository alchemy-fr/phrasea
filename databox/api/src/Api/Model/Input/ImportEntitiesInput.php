<?php

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints as Assert;

class ImportEntitiesInput
{
    #[Assert\All([
        new Assert\Type('string'),
    ])]
    #[Assert\Type('array')]
    public ?array $values = null;
}
