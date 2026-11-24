<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints as Assert;

class QuarantineBatchPrepareInput
{
    public const int MAX_ITEMS = 100;

    /**
     * IDs of the quarantined assets to check.
     *
     * @var string[]|null
     */
    #[Assert\NotNull]
    #[Assert\Count(min: 1, max: self::MAX_ITEMS)]
    #[Assert\All([new Assert\Type('string')])]
    public ?array $ids = null;
}
