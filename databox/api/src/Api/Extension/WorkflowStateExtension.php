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
 * an asset they can edit, and must filter on it (`?asset=`).
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

        if (!$this->isAdmin() && !$this->canEditFilteredAssets(null !== $operation ? self::getParameterValue($operation, 'asset') : null)) {
            $queryBuilder->andWhere('1 = 0');
        }

        $queryBuilder->addOrderBy($rootAlias.'.startedAt', 'DESC');
    }

    /**
     * The "asset" parameter: an ID or IRI, or a list of them (every asset must be editable).
     */
    private function canEditFilteredAssets(mixed $filter): bool
    {
        if (\is_array($filter)) {
            if ([] === $filter) {
                return false;
            }
            foreach ($filter as $value) {
                if (!$this->canEditFilteredAsset($value)) {
                    return false;
                }
            }

            return true;
        }

        return $this->canEditFilteredAsset($filter);
    }

    private function canEditFilteredAsset(mixed $filter): bool
    {
        if (!is_string($filter) || '' === $filter) {
            return false;
        }

        $assetId = basename($filter);
        if (!Uuid::isValid($assetId)) {
            return false;
        }
        $asset = $this->em->find(Asset::class, $assetId);

        return $asset instanceof Asset && $this->isGranted(AbstractVoter::EDIT, $asset);
    }
}
