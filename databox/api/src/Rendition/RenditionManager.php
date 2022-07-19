<?php

declare(strict_types=1);

namespace App\Rendition;

use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

class RenditionManager
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function getRenditionDefinitionByName(string $name, Workspace $workspace): RenditionDefinition
    {
        $definition = $this->em->getRepository(RenditionDefinition::class)
            ->findOneBy([
                'name' => $name,
                'workspace' => $workspace->getId(),
            ]);

        if (!$definition instanceof RenditionDefinition) {
            throw new InvalidArgumentException(sprintf('Rendition definition "%s" not found', $name));
        }

        return $definition;
    }
}
