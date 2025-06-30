<?php

declare(strict_types=1);

namespace App\Attribute;

use App\Entity\Core\AttributeDefinition;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AttributeManager
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function getAttributeDefinitionBySlug(string $workspaceId, string $slug): ?AttributeDefinition
    {
        return $this->em->getRepository(AttributeDefinition::class)->findOneBy([
            'slug' => $slug,
            'workspace' => $workspaceId,
        ]);
    }

    public function getAttributeDefinitions(string $workspaceId): iterable
    {
        return $this->em->getRepository(AttributeDefinition::class)->findAll([
            'workspace' => $workspaceId,
        ]);
    }
}
