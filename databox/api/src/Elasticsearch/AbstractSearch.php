<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Entity\Core\Workspace;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Util\SecurityAwareTrait;
use Doctrine\ORM\EntityManagerInterface;
use Elastica\Query;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractSearch
{
    use SecurityAwareTrait;

    protected EntityManagerInterface $em;

    protected function createACLBoolQuery(?string $userId, array $groupIds): ?Query\BoolQuery
    {
        if ($this->isSuperAdmin()) {
            return null;
        }

        $aclBoolQuery = new Query\BoolQuery();
        $shoulds = [];

        $publicWorkspaceIds = $this->getPublicWorkspaceIds();
        if (null !== $userId) {
            if (!empty($publicWorkspaceIds)) {
                $publicWorkspaceBoolQuery = new Query\BoolQuery();
                $publicWorkspaceBoolQuery->addMust(new Query\Terms('workspaceId', $publicWorkspaceIds));
                $publicWorkspaceBoolQuery->addMust(new Query\Range('privacy', [
                    'gte' => WorkspaceItemPrivacyInterface::PRIVATE,
                ]));
                $shoulds[] = $publicWorkspaceBoolQuery;
            }

            $allowedWorkspaceIds = $this->getAllowedWorkspaceIds($userId, $groupIds);
            if (!empty($allowedWorkspaceIds)) {
                $workspaceBoolQuery = new Query\BoolQuery();

                $workspaceBoolQuery->addMust(new Query\Terms('workspaceId', $allowedWorkspaceIds));
                $workspaceBoolQuery->addMust(new Query\Range('privacy', [
                    'gte' => WorkspaceItemPrivacyInterface::PRIVATE_IN_WORKSPACE,
                ]));

                $shoulds[] = $workspaceBoolQuery;
            }

            $shoulds[] = new Query\Term(['ownerId' => $userId]);
            $shoulds[] = new Query\Term(['users' => $userId]);
            if (!empty($groupIds)) {
                $shoulds[] = new Query\Terms('groups', $groupIds);
            }
        } else {
            if (!empty($publicWorkspaceIds)) {
                $publicWorkspaceBoolQuery = new Query\BoolQuery();
                $publicWorkspaceBoolQuery->addMust(new Query\Terms('workspaceId', $publicWorkspaceIds));
                $publicWorkspaceBoolQuery->addMust(new Query\Range('privacy', [
                    'gte' => WorkspaceItemPrivacyInterface::PUBLIC,
                ]));
                $shoulds[] = $publicWorkspaceBoolQuery;
            }
        }

        if (!empty($shoulds)) {
            foreach ($shoulds as $query) {
                $aclBoolQuery->addShould($query);
            }
        } else {
            $aclBoolQuery->addShould(new Query\Term(['workspaceId' => 'PUBLIC_WS']));
        }

        return $aclBoolQuery;
    }

    private function getAllowedWorkspaceIds(string $userId, array $groupIds): array
    {
        return $this->em->getRepository(Workspace::class)->getAllowedWorkspaceIds($userId, $groupIds);
    }

    private function getPublicWorkspaceIds(): array
    {
        return $this->em->getRepository(Workspace::class)->getPublicWorkspaceIds();
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
