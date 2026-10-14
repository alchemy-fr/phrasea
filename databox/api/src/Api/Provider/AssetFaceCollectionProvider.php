<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use App\Api\EntityIriConverter;
use App\Api\Traits\WorkspaceCollectionTrait;
use App\Entity\Core\AssetFace;
use App\Repository\Core\AssetFaceRepository;
use App\Security\Voter\AbstractVoter;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;

/**
 * Faces of the readable workspaces (or of the "workspace" parameter), newest assets first.
 * The faces of the assets the user cannot read are left out of the page.
 */
final class AssetFaceCollectionProvider extends AbstractCollectionProvider
{
    use SecurityAwareTrait;
    use WorkspaceCollectionTrait;

    private const int DEFAULT_LIMIT = 30;
    private const int MAX_LIMIT = 100;

    public function __construct(
        private readonly AssetFaceRepository $faceRepository,
        private readonly EntityIriConverter $entityIriConverter,
    ) {
    }

    protected function provideCollection(Operation $operation, array $uriVariables = [], array $context = []): array|object
    {
        $workspaces = $this->resolveAllowedWorkspaces($operation);
        if (empty($workspaces)) {
            return [];
        }

        $qb = $this->faceRepository->createWorkspacesQueryBuilder($workspaces)
            ->addSelect('a')
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'ASC')
            ->addOrderBy('f.position', 'ASC');

        $identity = self::getParameterValue($operation, 'identity');
        if (\is_string($identity) && '' !== $identity) {
            $qb
                ->andWhere('f.identity = :identity')
                ->setParameter('identity', $identity);
        }

        $identified = self::getParameterValue($operation, 'identified');
        if (null !== $identified && '' !== $identified) {
            $qb->andWhere(filter_var($identified, FILTER_VALIDATE_BOOL) ? 'f.identity IS NOT NULL' : 'f.identity IS NULL');
        }

        $origin = self::getParameterValue($operation, 'origin');
        if (in_array($origin, [AssetFace::IDENTITY_ORIGIN_USER, AssetFace::IDENTITY_ORIGIN_AUTO], true)) {
            $qb
                ->andWhere('f.identityOrigin = :origin')
                ->setParameter('origin', $origin);
        }

        $assetId = self::getParameterId($operation, 'asset');
        if (null !== $assetId) {
            $qb
                ->andWhere('f.asset = :asset')
                ->setParameter('asset', $assetId);
        }

        $query = self::getParameterValue($operation, 'query');
        if (\is_string($query) && '' !== trim($query)) {
            $qb
                ->andWhere('LOWER(f.identity) LIKE :query')
                ->setParameter('query', '%'.mb_strtolower(trim($query)).'%');
        }

        $limit = (int) self::getParameterValue($operation, 'limit', self::DEFAULT_LIMIT);
        $page = (int) self::getParameterValue($operation, 'page', 1);

        $pager = new Pagerfanta(new QueryAdapter($qb, false, false));
        $pager->setMaxPerPage(max(1, min(self::MAX_LIMIT, $limit)));
        $pager->setAllowOutOfRangePages(true);
        $pager->setCurrentPage(max(1, $page));

        return new FilteredPaginator(
            new PagerFantaApiPlatformPaginator($pager),
            fn (AssetFace $face): bool => $this->security->isGranted(AbstractVoter::READ, $face->getAsset()),
        );
    }
}
