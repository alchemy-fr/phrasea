<?php

declare(strict_types=1);

namespace App\Api\Extension;

use Alchemy\AclBundle\Mapping\ObjectMapping;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Core\RenditionDefinition;
use App\Repository\Core\AssetRepository;
use App\Repository\Core\RenditionDefinitionRepository;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\RenditionDefinitionVoter;
use App\Service\Asset\AssetPolicy\AssetPolicyManager;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\RequestStack;

class RenditionDefinitionExtension implements QueryCollectionExtensionInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly ObjectMapping $objectMapping,
        private readonly RenditionDefinitionRepository $renditionDefinitionRepository,
        private readonly AssetPolicyManager $assetPolicyManager,
        private readonly AssetRepository $assetRepository,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $this->addWhere($queryBuilder, $resourceClass);
    }

    private function addWhere(QueryBuilder $queryBuilder, string $resourceClass): void
    {
        if (RenditionDefinition::class !== $resourceClass) {
            return;
        }

        if (
            !$this->hasScope(AbstractVoter::LIST, RenditionDefinitionVoter::SCOPE_PREFIX)
            && !$this->isAdmin()
        ) {
            $user = $this->security->getUser();
            $userId = $user instanceof JwtUser ? $user->getId() : null;
            $groupIds = $user instanceof JwtUser ? $user->getGroups() : [];

            $this->renditionDefinitionRepository->addAclConditions($queryBuilder, $userId, $groupIds);
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($assetId = $request?->query->get('assetId')) {
            $asset = DoctrineUtil::findStrictByRepo(
                $this->assetRepository,
                $assetId,
            );
            $assetPolicyFilter = $this->assetPolicyManager->getPolicyApplicationFilter($asset);

            if (!empty($assetPolicyFilter->getFilteredRenditions())) {
                $queryBuilder->andWhere(
                    $queryBuilder->expr()->notIn(
                        $queryBuilder->getRootAliases()[0].'.id',
                        ':filteredRenditions'
                    )
                );
                $queryBuilder->setParameter('filteredRenditions', $assetPolicyFilter->getFilteredRenditions());
            }
        }
    }
}
