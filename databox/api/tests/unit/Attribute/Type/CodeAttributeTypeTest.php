<?php

declare(strict_types=1);

namespace App\Tests\Unit\Attribute\Type;

use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\CodeAttributeType;

class CodeAttributeTypeTest extends TextAttributeTypeTest
{
    #[\Override]
    protected function getType(): AttributeTypeInterface
    {
        return new CodeAttributeType();
    }
}
