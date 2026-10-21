<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\AttributeDefinitionVoter;
use App\Service\Asset\AssetPolicy\AssetPolicyManager;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;

/**
 * The attribute values of an asset (every value the user may see, unpaginated), or, with one or
 * more "definition", the values of these definitions across the assets (paginated, newest assets first,
 * the values of the assets the user cannot read left out of the page).
 */
class AttributeCollectionProvider extends AbstractAssetFilteredCollectionProvider
{
    private const int DEFAULT_LIMIT = 30;
    private const int MAX_LIMIT = 100;

    public function __construct(
        private readonly AssetPolicyManager $assetPolicyManager,
    ) {
    }

    protected function provideCollection(Operation $operation, array $uriVariables = [], array $context = []): array|object
    {
        $definitionIds = self::getParameterIds($operation, 'definition');
        if (!empty($definitionIds)) {
            return $this->provideByDefinitions($operation, $definitionIds);
        }

        $asset = $this->getAsset($operation);

        $attributes = $this->em->getRepository(Attribute::class)
            ->getCachedAssetAttributes($asset->getId())
        ;

        // Same visibility as the asset output: the definition policy and the asset policies.
        $filteredDefinitions = $this->assetPolicyManager->getPolicyApplicationFilter($asset)->getFilteredAttributes();
        $canView = [];

        return array_values(array_filter($attributes, function (Attribute $attribute) use ($filteredDefinitions, &$canView): bool {
            $definition = $attribute->getDefinition();
            $definitionId = $definition->getId();

            return !in_array($definitionId, $filteredDefinitions, true)
                && ($canView[$definitionId] ??= $this->security->isGranted(AttributeDefinitionVoter::VIEW_ATTRIBUTES, $definition));
        }));
    }

    /**
     * @param string[] $definitionIds
     */
    private function provideByDefinitions(Operation $operation, array $definitionIds): object
    {
        foreach ($definitionIds as $definitionId) {
            $definition = DoctrineUtil::findStrict($this->em, AttributeDefinition::class, $definitionId, throw404: true);
            $this->denyAccessUnlessGranted(AttributeDefinitionVoter::VIEW_ATTRIBUTES, $definition);
        }

        $qb = $this->em->getRepository(Attribute::class)->createQueryBuilder('a')
            ->innerJoin('a.asset', 'asset')
            ->addSelect('asset')
            ->andWhere('a.definition IN (:defs)')
            ->andWhere('asset.deletedAt IS NULL')
            ->setParameter('defs', $definitionIds)
            ->orderBy('asset.createdAt', 'DESC')
            ->addOrderBy('asset.id', 'ASC')
            ->addOrderBy('a.position', 'ASC')
            ->addOrderBy('a.createdAt', 'ASC');

        $assetId = self::getParameterId($operation, 'assetId') ?? self::getParameterId($operation, 'asset');
        if (null !== $assetId) {
            $qb
                ->andWhere('a.asset = :asset')
                ->setParameter('asset', $assetId);
        }

        $values = self::getParameterValue($operation, 'value');
        $values = array_values(array_filter(\is_array($values) ? $values : [$values], static fn (mixed $v): bool => \is_string($v) && '' !== $v));
        if (!empty($values)) {
            $qb
                ->andWhere('a.value IN (:values)')
                ->setParameter('values', $values);
        }

        $origin = self::getParameterValue($operation, 'origin');
        if (\is_string($origin) && '' !== $origin) {
            // The parameter schema already rejects unknown labels (422)
            $qb
                ->andWhere('a.origin = :origin')
                ->setParameter('origin', array_search($origin, Attribute::ORIGIN_LABELS, true));
        }

        $limit = (int) self::getParameterValue($operation, 'limit', self::DEFAULT_LIMIT);
        $page = (int) self::getParameterValue($operation, 'page', 1);

        $pager = new Pagerfanta(new QueryAdapter($qb, false, false));
        $pager->setMaxPerPage(max(1, min(self::MAX_LIMIT, $limit)));
        $pager->setAllowOutOfRangePages(true);
        $pager->setCurrentPage(max(1, $page));

        return new FilteredPaginator(
            new PagerFantaApiPlatformPaginator($pager),
            fn (Attribute $attribute): bool => $this->security->isGranted(AbstractVoter::READ, $attribute),
        );
    }
}
