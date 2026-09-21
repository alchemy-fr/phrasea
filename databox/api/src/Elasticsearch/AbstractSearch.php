<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Elasticsearch\Exception\MissingSearchIndexException;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Repository\Core\WorkspaceRepository;
use Elastica\Query;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractSearch
{
    use SecurityAwareTrait;
    final public const string NO_AUTH = '__no_auth__';

    protected WorkspaceRepository $workspaceRepository;

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
    protected static function toIdList(mixed $value): array
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
     * @throws NoWorkspaceAllowedException
     */
    protected function createACLBoolQuery(?string $userId, array $groupIds): ?Query\BoolQuery
    {
        if ($this->isAdmin()) {
            return null;
        }

        if (null !== $adminScope = $this->getAdminScope()) {
            if ($this->hasScope($adminScope, null, false)) {
                return null;
            }
        }

        $workspacesQuery = new Query\BoolQuery();

        $should = [];
        $permittedWorkspaces = $publicWorkspaceIds = $this->workspaceRepository->getPublicWorkspaceIds();
        if (null !== $userId) {
            if (!empty($publicWorkspaceIds)) {
                $publicWorkspaceBoolQuery = new Query\BoolQuery();
                $publicWorkspaceBoolQuery->addMust(new Query\Terms('workspaceId', $publicWorkspaceIds));
                $publicWorkspaceBoolQuery->addMust(new Query\Range('privacy', [
                    'gte' => WorkspaceItemPrivacyInterface::PRIVATE,
                ]));
                $should[] = $publicWorkspaceBoolQuery;
            }

            $allowedWorkspaceIds = $this->workspaceRepository->getAllowedWorkspaceIds($userId, $groupIds, $this->isAdmin());
            if (!empty($allowedWorkspaceIds)) {
                $permittedWorkspaces = array_merge($permittedWorkspaces, $allowedWorkspaceIds);
                $workspaceBoolQuery = new Query\BoolQuery();

                $workspaceBoolQuery->addMust(new Query\Terms('workspaceId', $allowedWorkspaceIds));
                $workspaceBoolQuery->addMust(new Query\Range('privacy', [
                    'gte' => WorkspaceItemPrivacyInterface::PRIVATE_IN_WORKSPACE,
                ]));

                $should[] = $workspaceBoolQuery;
            }

            $should[] = new Query\Term(['ownerId' => $userId]);
            $should[] = new Query\Term(['users' => $userId]);
            if (!empty($groupIds)) {
                $should[] = new Query\Terms('groups', $groupIds);
            }
        } else {
            if (!empty($publicWorkspaceIds)) {
                $publicWorkspaceBoolQuery = new Query\BoolQuery();
                $publicWorkspaceBoolQuery->addMust(new Query\Terms('workspaceId', $publicWorkspaceIds));
                $publicWorkspaceBoolQuery->addMust(new Query\Range('privacy', [
                    'gte' => WorkspaceItemPrivacyInterface::PUBLIC,
                ]));
                $should[] = $publicWorkspaceBoolQuery;
            }
        }

        $permittedWorkspaces = array_values(array_unique($permittedWorkspaces));
        if (empty($permittedWorkspaces)) {
            throw new NoWorkspaceAllowedException();
        }

        $workspacesQuery->addMust(new Query\Terms('workspaceId', $permittedWorkspaces));

        if (!empty($should)) {
            $aclQuery = new Query\BoolQuery();
            foreach ($should as $query) {
                $aclQuery->addShould($query);
            }
            $workspacesQuery->addMust($aclQuery);
        }

        return $workspacesQuery;
    }

    protected function getAdminScope(): ?string
    {
        return null;
    }

    #[Required]
    public function setWorkspaceRepository(WorkspaceRepository $workspaceRepository): void
    {
        $this->workspaceRepository = $workspaceRepository;
    }
}
