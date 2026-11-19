<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use App\Elasticsearch\Exception\MissingSearchIndexException;
use App\Elasticsearch\Filter\ElasticsearchParameterApplier;
use App\Elasticsearch\Filter\SearchQuery;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Repository\Core\WorkspaceRepository;
use App\Security\Voter\AbstractVoter;
use Elastica\Query;
use Pagerfanta\Pagerfanta;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractSearch
{
    use SecurityAwareTrait;
    final public const string NO_AUTH = '__no_auth__';

    /**
     * The index.max_result_window of Elasticsearch: a page cannot end beyond it.
     */
    final public const int MAX_RESULT_WINDOW = 10000;

    protected WorkspaceRepository $workspaceRepository;
    protected ElasticsearchParameterApplier $parameterApplier;

    /**
     * Applies the query parameter filters declared on the operation (see ElasticsearchFilterInterface).
     *
     * A null operation (search run outside API Platform) applies nothing.
     *
     * @param array<string, mixed> $context
     */
    protected function applyParameters(SearchQuery $query, string $resourceClass, ?Operation $operation, array $context = []): void
    {
        if (null === $operation) {
            return;
        }

        $this->parameterApplier->apply($query, $resourceClass, $operation, $context);
    }

    /**
     * Applies the `limit` and `page` options, capping the page size.
     *
     * @param array<string, mixed> $options
     */
    protected static function applyPagination(Pagerfanta $pager, array $options, int $maxLimit): void
    {
        $limit = (int) ($options['limit'] ?? $maxLimit);
        if ($limit <= 0 || $limit > $maxLimit) {
            $limit = $maxLimit;
        }
        $pager->setMaxPerPage($limit);

        $page = (int) ($options['page'] ?? 1);
        if ($page > 1) {
            self::assertWithinResultWindow($page, $limit);
            $pager->setAllowOutOfRangePages(true);
            $pager->setCurrentPage($page);
        }
    }

    /**
     * Validates the `limit` and `page` options: a page size above the maximum is capped.
     *
     * @param array<string, mixed> $options
     *
     * @return array{int, int} the page size and the page number
     *
     * @throws BadRequestHttpException
     */
    protected static function resolvePagination(array $options, int $maxLimit): array
    {
        $limit = $maxLimit;
        if (null !== ($options['limit'] ?? null)) {
            $limit = filter_var($options['limit'], FILTER_VALIDATE_INT);
            if (false === $limit || $limit < 1) {
                throw new BadRequestHttpException('"limit" must be a positive integer');
            }
            $limit = min($limit, $maxLimit);
        }

        $page = 1;
        if (null !== ($options['page'] ?? null)) {
            $page = filter_var($options['page'], FILTER_VALIDATE_INT);
            if (false === $page || $page < 1) {
                throw new BadRequestHttpException('"page" must be a positive integer');
            }
        }

        self::assertWithinResultWindow($page, $limit);

        return [$limit, $page];
    }

    /**
     * @throws BadRequestHttpException
     */
    private static function assertWithinResultWindow(int $page, int $limit): void
    {
        if ($page > intdiv(self::MAX_RESULT_WINDOW, $limit)) {
            throw new BadRequestHttpException(sprintf('Only the first %d results can be paginated: narrow the search to reach the next ones', self::MAX_RESULT_WINDOW));
        }
    }

    /**
     * Normalizes a query parameter declared as `array<string>` into an actual list.
     *
     * A client sending `?workspaces=<id>` instead of `?workspaces[]=<id>` hands us
     * a plain string, which a terms query — and any repository lookup by ids —
     * rejects with a TypeError. Empty values are dropped so an explicit `?ids=`
     * does not produce a filter matching nothing by accident.
     *
     * @return list<string>
     */
    public static function toIdList(mixed $value): array
    {
        if (null === $value) {
            return [];
        }

        return array_values(array_filter(
            array_map(
                static fn (mixed $v): string => \is_scalar($v) ? (string) $v : '',
                \is_array($value) ? $value : [$value]
            ),
            static fn (string $v): bool => '' !== $v
        ));
    }

    /**
     * Runs the callable that actually hits Elasticsearch, translating a missing
     * index into MissingSearchIndexException so API providers can degrade to an
     * empty result instead of returning a 500.
     *
     * @template T
     *
     * @param \Closure(): T $run
     *
     * @return T
     *
     * @throws MissingSearchIndexException
     */
    protected function executeSearch(\Closure $run): mixed
    {
        try {
            return $run();
        } catch (\Throwable $e) {
            if (null !== $missing = MissingSearchIndexException::tryFrom($e)) {
                throw $missing;
            }

            throw $e;
        }
    }

    /**
     * The documents the user may see, as far as their access rights go.
     *
     * In the workspaces readable by the user, the documents whose privacy is at least
     * $memberMinPrivacy are open to them: the voter of the indexed items decides that threshold
     * (a collection lists its name from PRIVATE_IN_WORKSPACE, an asset opens its content from
     * PUBLIC_IN_WORKSPACE). Anonymous users only see the PUBLIC documents of the public workspaces.
     *
     * @throws NoWorkspaceAllowedException
     */
    protected function createACLBoolQuery(?string $userId, array $groupIds, int $memberMinPrivacy): ?Query\BoolQuery
    {
        if ($this->isAdmin()) {
            return null;
        }

        if (null !== $listedSubject = $this->createListedSubject()) {
            if ($this->isGranted(AbstractVoter::LIST, $listedSubject)) {
                return null;
            }
        }

        $should = [];
        if (null !== $userId) {
            // The readable workspaces include the public ones (whose terms the user signed)
            $permittedWorkspaces = $this->workspaceRepository->getReadableWorkspaceIds($userId, $groupIds, false);
            if (!empty($permittedWorkspaces)) {
                $workspaceBoolQuery = new Query\BoolQuery();
                $workspaceBoolQuery->addMust(new Query\Terms('workspaceId', $permittedWorkspaces));
                $workspaceBoolQuery->addMust(new Query\Range('privacy', [
                    'gte' => $memberMinPrivacy,
                ]));
                $should[] = $workspaceBoolQuery;
            }

            $should[] = new Query\Term(['ownerId' => $userId]);
            // An ACE given to all users is indexed under the wildcard: it applies to any authenticated user
            $should[] = new Query\Terms('users', [$userId, AccessControlEntryInterface::USER_WILDCARD]);
            $should[] = new Query\Terms('groups', [...$groupIds, AccessControlEntryInterface::USER_WILDCARD]);
        } else {
            $permittedWorkspaces = $this->workspaceRepository->getPublicWorkspaceIds();
            if (!empty($permittedWorkspaces)) {
                $publicWorkspaceBoolQuery = new Query\BoolQuery();
                $publicWorkspaceBoolQuery->addMust(new Query\Terms('workspaceId', $permittedWorkspaces));
                $publicWorkspaceBoolQuery->addMust(new Query\Range('privacy', [
                    'gte' => WorkspaceItemPrivacyInterface::PUBLIC,
                ]));
                $should[] = $publicWorkspaceBoolQuery;
            }
        }

        if (empty($permittedWorkspaces)) {
            throw new NoWorkspaceAllowedException();
        }

        $aclQuery = new Query\BoolQuery();
        foreach ($should as $query) {
            $aclQuery->addShould($query);
        }

        $workspacesQuery = new Query\BoolQuery();
        $workspacesQuery->addMust(new Query\Terms('workspaceId', $permittedWorkspaces));
        $workspacesQuery->addMust($aclQuery);

        return $workspacesQuery;
    }

    /**
     * A new document of the searched kind, on which LIST is voted to skip the ACL filtering.
     */
    protected function createListedSubject(): ?object
    {
        return null;
    }

    #[Required]
    public function setWorkspaceRepository(WorkspaceRepository $workspaceRepository): void
    {
        $this->workspaceRepository = $workspaceRepository;
    }

    #[Required]
    public function setParameterApplier(ElasticsearchParameterApplier $parameterApplier): void
    {
        $this->parameterApplier = $parameterApplier;
    }
}
