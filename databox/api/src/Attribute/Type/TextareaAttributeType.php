<?php

declare(strict_types=1);

namespace App\Attribute\Type;

class TextareaAttributeType extends TextAttributeType
{
    public const string NAME = 'textarea';

    public function supportsAggregation(): bool
    {
        return false;
    }

    public function supportsSuggest(): bool
    {
        return false;
    }
}
