<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Crop rectangle, relative to the displayed media (0..1).
 */
class ClipCropInput
{
    private const float EPSILON = 1e-6;

    #[Assert\NotNull]
    #[Assert\Range(min: 0, max: 1)]
    public ?float $x = null;

    #[Assert\NotNull]
    #[Assert\Range(min: 0, max: 1)]
    public ?float $y = null;

    #[Assert\NotNull]
    #[Assert\Positive]
    #[Assert\LessThanOrEqual(1)]
    public ?float $w = null;

    #[Assert\NotNull]
    #[Assert\Positive]
    #[Assert\LessThanOrEqual(1)]
    public ?float $h = null;

    #[Assert\Callback]
    public function validateBounds(ExecutionContextInterface $context): void
    {
        if (null !== $this->x && null !== $this->w && $this->x + $this->w > 1 + self::EPSILON) {
            $context->buildViolation('The crop exceeds the right edge of the media.')
                ->atPath('w')
                ->addViolation();
        }
        if (null !== $this->y && null !== $this->h && $this->y + $this->h > 1 + self::EPSILON) {
            $context->buildViolation('The crop exceeds the bottom edge of the media.')
                ->atPath('h')
                ->addViolation();
        }
    }

    public function toArray(): array
    {
        return [
            'x' => $this->x,
            'y' => $this->y,
            'w' => $this->w,
            'h' => $this->h,
        ];
    }
}
