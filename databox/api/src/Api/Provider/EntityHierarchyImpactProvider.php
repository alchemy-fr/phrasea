<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Model\Output\EntityHierarchyImpactOutput;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\AttributeEntity;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Repository\Core\AttributeEntityRepository;
use App\Repository\Core\AttributeRepository;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;

final class EntityHierarchyImpactProvider implements ProviderInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AttributeEntityRepository $attributeEntityRepository,
        private readonly AttributeDefinitionRepository $attributeDefinitionRepository,
        private readonly AttributeRepository $attributeRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): EntityHierarchyImpactOutput
    {
        $entity = DoctrineUtil::findStrict($this->em, AttributeEntity::class, (string) $uriVariables['id'], throw404: true);
        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $entity);

        $output = new EntityHierarchyImpactOutput();
        $output->id = $entity->getId();

        $ids = $this->attributeEntityRepository->getDescendantIds([$entity->getId()]);
        $output->entityCount = \count($ids);

        $definitions = $this->attributeDefinitionRepository->getWorkspaceDefinitionOfEntity($entity->getWorkspaceId(), $entity->getListId());
        $usage = $this->attributeRepository->countUsage(array_map(fn (AttributeDefinition $d): string => $d->getId(), $definitions), $ids);
        foreach ($usage as $u) {
            $output->attributeCount += $u['attributes'];
            $output->humanAttributeCount += $u['humanAttributes'];
        }

        foreach ($definitions as $definition) {
            if ($definition->isHierarchical()) {
                $output->definitions[] = [
                    'id' => $definition->getId(),
                    'name' => $definition->getName(),
                    'depth' => $definition->getEntityHierarchyDepth(),
                    'autoFillParents' => $definition->isAutoFillParents(),
                ];
            }
        }

        return $output;
    }
}
