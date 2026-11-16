<?php

declare(strict_types=1);

namespace App\Api\Extension;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Api\Traits\ParameterValuesTrait;
use App\Entity\Core\Asset;
use App\Entity\Workflow\WorkflowState;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\Uuid;

/**
 * Every workflow run is listed to admins; the other users only get the runs of
 * the assets they can edit, and must filter on them (`?asset=`).
 */
class WorkflowStateExtension implements QueryCollectionExtensionInterface
{
    use ParameterValuesTrait;
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (WorkflowState::class !== $resourceClass) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];

        if (!$this->isAdmin()) {
            $assetIds = $this->getEditableAssetIds(null !== $operation ? self::getParameterValue($operation, 'asset') : null);
            if (null === $assetIds) {
                $queryBuilder->andWhere('1 = 0');
            } else {
                // Restricted on the checked IDs themselves: the "asset" filter skips the values with a non-integer key (`?asset[x]=`)
                $parameterName = $queryNameGenerator->generateParameterName('editableAssets');
                $queryBuilder
                    ->andWhere(sprintf('%s.asset IN (:%s)', $rootAlias, $parameterName))
                    ->setParameter($parameterName, $assetIds);
            }
        }

        $queryBuilder->addOrderBy($rootAlias.'.startedAt', 'DESC');
    }

    /**
     * The "asset" parameter: an ID or IRI, or a list of them, whatever their keys.
     *
     * @return string[]|null the IDs of the assets, null unless every one of them is editable
     */
    private function getEditableAssetIds(mixed $filter): ?array
    {
        $values = \is_array($filter) ? array_values($filter) : [$filter];
        if ([] === $values) {
            return null;
        }

        $assetIds = [];
        foreach ($values as $value) {
            $assetId = $this->getEditableAssetId($value);
            if (null === $assetId) {
                return null;
            }
            $assetIds[] = $assetId;
        }

        return array_values(array_unique($assetIds));
    }

    private function getEditableAssetId(mixed $filter): ?string
    {
        if (!\is_string($filter) || '' === $filter) {
            return null;
        }

        $assetId = basename($filter);
        if (!Uuid::isValid($assetId)) {
            return null;
        }
        $asset = $this->em->find(Asset::class, $assetId);

        return $asset instanceof Asset && $this->isGranted(AbstractVoter::EDIT, $asset) ? $asset->getId() : null;
    }
}
