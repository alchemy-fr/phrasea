<?php

declare(strict_types=1);

namespace App\Controller\Core;

use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\RenditionDefinition;
use Symfony\Component\HttpFoundation\Request;

class RenditionDefinitionSortAction extends AbstractSortAction
{
    public function __invoke(Request $request): void
    {
        $this->sort($request, RenditionDefinition::class, 'priority', true);
    }
}
