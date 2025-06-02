<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints as Assert;

class RemoveFromAttributeListInput
{
    /**
     * @var string[]
     */
    #[Assert\NotNull]
    public ?array $items = null;
}
