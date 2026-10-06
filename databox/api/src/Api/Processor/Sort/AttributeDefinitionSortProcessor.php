<?php

declare(strict_types=1);

namespace App\Api\Processor\Sort;

use App\Entity\Core\AttributeDefinition;

final class AttributeDefinitionSortProcessor extends AbstractSortProcessor
{
    protected function getClass(): string
    {
        return AttributeDefinition::class;
    }

    protected function getPositionField(): string
    {
        return 'position';
    }
}
