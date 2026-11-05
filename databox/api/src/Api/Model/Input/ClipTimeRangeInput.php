<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints as Assert;

class ClipTimeRangeInput
{
    /**
     * Start, in seconds.
     */
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    public ?float $s = null;

    /**
     * End, in seconds.
     */
    #[Assert\NotNull]
    #[Assert\GreaterThan(propertyPath: 's')]
    public ?float $e = null;

    public function toArray(): array
    {
        return [
            's' => $this->s,
            'e' => $this->e,
        ];
    }
}
