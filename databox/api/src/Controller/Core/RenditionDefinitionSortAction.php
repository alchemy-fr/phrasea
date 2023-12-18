<?php

declare(strict_types=1);

namespace App\Controller\Core;

use App\Entity\Core\RenditionDefinition;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RenditionDefinitionSortAction extends AbstractSortAction
{
    public function __invoke(Request $request): Response
    {
        return $this->sort($request, RenditionDefinition::class, 'priority', true);
    }
}
