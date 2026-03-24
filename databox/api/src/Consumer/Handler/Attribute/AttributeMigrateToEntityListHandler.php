<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Attribute;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Attribute\Type\EntityAttributeType;
use App\Entity\Core\AttributeDefinition;
use App\Repository\Core\AttributeDefinitionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class AttributeMigrateToEntityListHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private AttributeDefinitionRepository $attributeDefinitionRepository,
    ) {
    }

    public function __invoke(AttributeMigrateToEntityList $message): void
    {
        /** @var AttributeDefinition $attributeDefinition */
        $attributeDefinition = DoctrineUtil::findStrictByRepo($this->attributeDefinitionRepository, $message->id);

        if ($attributeDefinition->getFieldType() !== EntityAttributeType::getName()) {
            throw new \LogicException(sprintf('Attribute definition "%s" is not of type "%s"', $attributeDefinition->getId(), EntityAttributeType::getName()));
        }
        $entityList = $attributeDefinition->getEntityList();
        if (empty($entityList)) {
            throw new \LogicException(sprintf('Attribute definition "%s" does not have an entity list', $attributeDefinition->getId()));
        }

        $this->em->wrapInTransaction(function () use ($attributeDefinition) {
            $this->em->getConnection()->executeQuery(<<<SQL
                INSERT INTO attribute_entity (id, position, list_id, workspace_id, value, updated_at, created_at)
                SELECT gen_random_uuid(), position, :list, :ws, value, updated_at, created_at
                FROM attribute
                WHERE definition_id = :def AND value IS NOT NULL AND value != '';
            SQL, [
                'ws' => $attributeDefinition->getWorkspaceId(),
                'list' => $attributeDefinition->getEntityList()->getId(),
                'def' => $attributeDefinition->getId(),
            ]);

            $this->em->getConnection()->executeQuery(<<<SQL
                UPDATE attribute a
                SET value = e.id::text
                FROM attribute_entity e
                WHERE a.definition_id = :def AND e.list_id = :list AND a.value = e.value;
            SQL, [
                'ws' => $attributeDefinition->getWorkspaceId(),
                'list' => $attributeDefinition->getEntityList()->getId(),
                'def' => $attributeDefinition->getId(),
            ]);
        });

    }
}
