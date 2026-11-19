<?php

declare(strict_types=1);

namespace App\Api\Extension;

use Alchemy\AclBundle\Mapping\ObjectMapping;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\UuidUtil;
use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Api\Traits\ParameterValuesTrait;
use App\Entity\Core\RenditionDefinition;
use App\Repository\Core\AssetRepository;
use App\Repository\Core\RenditionDefinitionRepository;
use App\Security\Voter\AbstractVoter;
use App\Service\Asset\AssetPolicy\AssetPolicyManager;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class RenditionDefinitionExtension implements QueryCollectionExtensionInterface
{
    use ParameterValuesTrait;
    use SecurityAwareTrait;

    public function __construct(
        private readonly ObjectMapping $objectMapping,
        private readonly RenditionDefinitionRepository $renditionDefinitionRepository,
        private readonly AssetPolicyManager $assetPolicyManager,
        private readonly AssetRepository $assetRepository,
    ) {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $this->addWhere($queryBuilder, $resourceClass, $operation);
    }

    private function addWhere(QueryBuilder $queryBuilder, string $resourceClass, ?Operation $operation): void
    {
        if (RenditionDefinition::class !== $resourceClass) {
            return;
        }

        if (!$this->isGranted(AbstractVoter::LIST, new RenditionDefinition())) {
            $user = $this->security->getUser();
            $userId = $user instanceof JwtUser ? $user->getId() : null;
            $groupIds = $user instanceof JwtUser ? $user->getGroups() : [];

            $this->renditionDefinitionRepository->addAclConditions($queryBuilder, $userId, $groupIds);
        }

        $assetId = null !== $operation ? self::getParameterValue($operation, 'assetId') : null;
        if (null !== $assetId && '' !== $assetId) {
            // An unknown asset and an unreadable one answer the same, so that the response tells nothing about its existence
            $asset = $this->assetRepository->find(UuidUtil::assertValid($assetId, 'assetId'));
            if (null === $asset || !$this->isGranted(AbstractVoter::READ, $asset)) {
                throw new NotFoundHttpException('Asset not found');
            }

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
