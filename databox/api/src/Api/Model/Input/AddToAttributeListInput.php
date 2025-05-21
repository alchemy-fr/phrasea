<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints as Assert;

class AddToAttributeListInput
{
    /**
     * @var AttributeListItemInput[]
     */
    #[Assert\NotNull]
    #[Assert\Valid]
    public ?array $items = null;
}
