<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints as Assert;

trait IdsInputTrait
{
    #[Assert\NotNull]
    #[Assert\Count(min: 1)]
    public ?array $ids = null;
}
