<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Entity\Core\Workspace;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Elastica\Query;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractSearch
{
    use SecurityAwareTrait;

    protected EntityManagerInterface $em;

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

        $aclBoolQuery = new Query\BoolQuery();
        $should = [];

        $publicWorkspaceIds = $this->getPublicWorkspaceIds();
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

        if (!empty($should)) {
            foreach ($should as $query) {
                $aclBoolQuery->addShould($query);
            }
        } else {
            $aclBoolQuery->addShould(new Query\Term(['workspaceId' => 'PUBLIC_WS']));
        }

        return $aclBoolQuery;
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

        return $this->allowedWorkspaces = $this->em->getRepository(Workspace::class)->getAllowedWorkspaceIds($userId, $groupIds);
    }

    protected function getPublicWorkspaceIds(): array
    {
        if (null !== $this->publicWorkspaceIds) {
            return $this->publicWorkspaceIds;
        }

        return $this->publicWorkspaceIds = $this->em->getRepository(Workspace::class)->getPublicWorkspaceIds();
    }

    protected function findEntityByIds(string $entityName, array $ids): array
    {
        return $this->em
            ->createQueryBuilder()
            ->select('t')
            ->from($entityName, 't')
            ->where('t.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    #[Required]
    public function setEm(EntityManagerInterface $em): void
    {
        $this->em = $em;
    }
}
