<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: self::NAME)]
class TextareaAttributeType extends TextAttributeType
{
    public const string NAME = 'textarea';

    #[\Override]
    public function supportsAggregation(): bool
    {
        return false;
    }

    #[\Override]
    public function supportsSuggest(): bool
    {
        return false;
    }
}
