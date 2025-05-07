<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class AttributeListItemInput
{
    public ?string $id = null;
    public ?string $definition = null;
    public ?string $key = null;

    #[Assert\NotNull]
    public ?int $type = null;
}
