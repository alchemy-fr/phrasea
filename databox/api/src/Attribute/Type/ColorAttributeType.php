<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: self::NAME)]
class ColorAttributeType extends KeywordAttributeType
{
    public const string NAME = 'color';

    #[\Override]
    public function isLocaleAware(): bool
    {
        return true;
    }
}
