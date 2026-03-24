<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Attribute;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Attribute\Type\EntityAttributeType;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\EntityList;
use App\Repository\Core\AttributeDefinitionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class AttributeMigrateFromEntityListHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private AttributeDefinitionRepository $attributeDefinitionRepository,
    ) {
    }

    public function __invoke(AttributeMigrateFromEntityList $message): void
    {
        /** @var AttributeDefinition $attributeDefinition */
        $attributeDefinition = DoctrineUtil::findStrictByRepo($this->attributeDefinitionRepository, $message->id);

        if ($attributeDefinition->getFieldType() === EntityAttributeType::getName()) {
            throw new \LogicException(sprintf('Attribute definition "%s" is of type "%s"', $attributeDefinition->getId(), EntityAttributeType::getName()));
        }
        $entityList = DoctrineUtil::findStrict($this->em, EntityList::class, $message->listId);

        $this->em->getConnection()->executeQuery(<<<SQL
                UPDATE attribute a
                SET value = e.value
                FROM attribute_entity e
                WHERE a.definition_id = :def AND e.list_id = :list AND e.id = a.value::uuid;
            SQL, [
            'ws' => $attributeDefinition->getWorkspaceId(),
            'list' => $entityList->getId(),
            'def' => $attributeDefinition->getId(),
        ]);

    }
}
