<?php

declare(strict_types=1);

namespace App\Api\Processor\Sort;

use App\Entity\Core\RenditionDefinition;

final class RenditionDefinitionSortProcessor extends AbstractSortProcessor
{
    protected function getClass(): string
    {
        return RenditionDefinition::class;
    }

    protected function getPositionField(): string
    {
        return 'priority';
    }

    #[\Override]
    protected function isReversed(): bool
    {
        return true;
    }
}
