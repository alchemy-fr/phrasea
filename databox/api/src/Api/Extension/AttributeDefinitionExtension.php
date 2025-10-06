<?php

declare(strict_types=1);

namespace App\Api\Extension;

use Alchemy\AclBundle\Mapping\ObjectMapping;
use Alchemy\AuthBundle\Security\JwtUser;
use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Core\AttributeDefinition;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Security\ScopeTrait;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\AssetDataTemplateVoter;
use Doctrine\ORM\QueryBuilder;

class AttributeDefinitionExtension implements QueryCollectionExtensionInterface
{
    use ScopeTrait;

    public function __construct(
        private readonly ObjectMapping $objectMapping,
        private readonly AttributeDefinitionRepository $attributeDefinitionRepository,
    ) {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $this->addWhere($queryBuilder, $resourceClass, $context);
    }

    private function addWhere(QueryBuilder $queryBuilder, string $resourceClass, array $context): void
    {
        if (AttributeDefinition::class !== $resourceClass) {
            return;
        }

        if (!$this->hasScope(AbstractVoter::LIST, AssetDataTemplateVoter::SCOPE_PREFIX)) {
            $user = $this->security->getUser();
            $userId = $user instanceof JwtUser ? $user->getId() : null;
            $groupIds = $user instanceof JwtUser ? $user->getGroups() : [];

            $this->attributeDefinitionRepository->addAclConditions($queryBuilder, $userId, $groupIds);
        }
    }
}
