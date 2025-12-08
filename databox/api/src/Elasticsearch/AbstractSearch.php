<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Repository\Core\WorkspaceRepository;
use Elastica\Query;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractSearch
{
    use SecurityAwareTrait;

    protected WorkspaceRepository $workspaceRepository;

    private ?array $allowedWorkspaces = null;
    private ?array $publicWorkspaceIds = null;

    protected function createACLBoolQuery(?string $userId, array $groupIds): ?Query\BoolQuery
    {
        if ($this->isSuperAdmin()) {
            return null;
        }

        if (null !== $adminScope = $this->getAdminScope()) {
            if ($this->hasScope($adminScope)) {
                return null;
            }
        }

        $workspacesQuery = new Query\BoolQuery();

        $should = [];
        $permittedWorkspaces = $publicWorkspaceIds = $this->getPublicWorkspaceIds();
        if (null !== $userId) {
            if (!empty($publicWorkspaceIds)) {
                $publicWorkspaceBoolQuery = new Query\BoolQuery();
                $publicWorkspaceBoolQuery->addMust(new Query\Terms('workspaceId', $publicWorkspaceIds));
                $publicWorkspaceBoolQuery->addMust(new Query\Range('privacy', [
                    'gte' => WorkspaceItemPrivacyInterface::PRIVATE,
                ]));
                $should[] = $publicWorkspaceBoolQuery;
            }

            $allowedWorkspaceIds = $this->getAllowedWorkspaceIds($userId, $groupIds);
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
            $workspacesQuery->addMust(new Query\Term(['_id' => '__no_such_id__']));

            return $workspacesQuery;
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

    protected function getAllowedWorkspaceIds(string $userId, array $groupIds): array
    {
        if (null !== $this->allowedWorkspaces) {
            return $this->allowedWorkspaces;
        }

        return $this->allowedWorkspaces = $this->workspaceRepository->getAllowedWorkspaceIds($userId, $groupIds);
    }

    protected function getPublicWorkspaceIds(): array
    {
        if (null !== $this->publicWorkspaceIds) {
            return $this->publicWorkspaceIds;
        }

        return $this->publicWorkspaceIds = $this->workspaceRepository->getPublicWorkspaceIds();
    }

    #[Required]
    public function setWorkspaceRepository(WorkspaceRepository $workspaceRepository): void
    {
        $this->workspaceRepository = $workspaceRepository;
    }
}
