<?php

declare(strict_types=1);

namespace App\Tests\Attribute\Type;

use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\TextareaAttributeType;

class TextareaAttributeTypeTest extends TextAttributeTypeTest
{
    #[\Override]
    protected function getType(): AttributeTypeInterface
    {
        return new TextareaAttributeType();
    }
}
