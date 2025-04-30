<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints as Assert;

class DefinitionToAttributeListInput
{
    #[Assert\NotNull]
    public ?string $id = null;
}
