<?php

declare(strict_types=1);

namespace App\Controller\Core;

use App\Entity\Core\AttributeDefinition;
use Symfony\Component\HttpFoundation\Request;

class AttributeDefinitionSortAction extends AbstractSortAction
{
    public function __invoke(Request $request): void
    {
        $this->sort($request, AttributeDefinition::class, 'position');
    }
}
