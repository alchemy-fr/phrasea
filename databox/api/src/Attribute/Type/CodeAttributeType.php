<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: self::NAME)]
class CodeAttributeType extends TextareaAttributeType
{
    public const string NAME = 'code';

    #[\Override]
    public function isMappingLocaleAware(): bool
    {
        return false;
    }
}
