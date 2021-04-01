<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Entity\Core\Workspace;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Elastica\Query;

abstract class AbstractSearch
{
    private EntityManagerInterface $em;

    public function createACLBoolQuery(?string $userId, array $groupIds): Query\BoolQuery
    {
        $aclBoolQuery = new Query\BoolQuery();

        $shoulds = [
            new Query\Range('privacy', [
                'gte' => WorkspaceItemPrivacyInterface::PUBLIC,
            ]),
        ];

        if (null !== $userId) {
            $shoulds[] = new Query\Range('privacy', [
                'gte' => WorkspaceItemPrivacyInterface::PRIVATE,
            ]);

            $allowedWorkspaceIds = $this->getAllowedWorkspaceIds($userId, $groupIds);
            if (!empty($allowedWorkspaceIds)) {
                $workspaceBoolQuery = new Query\BoolQuery();

                $workspaceBoolQuery->addMust(new Query\Range('privacy', [
                    'gte' => WorkspaceItemPrivacyInterface::PRIVATE_IN_WORKSPACE,
                ]));
                $workspaceBoolQuery->addMust(new Query\Terms('workspaceId', $allowedWorkspaceIds));

                $shoulds[] = $workspaceBoolQuery;
            }

            $shoulds[] = new Query\Term(['ownerId' => $userId]);
            $shoulds[] = new Query\Term(['users' => $userId]);
            if (!empty($groupIds)) {
                $shoulds[] = new Query\Terms('groups', $groupIds);
            }
        }

        foreach ($shoulds as $query) {
            $aclBoolQuery->addShould($query);
        }

        return $aclBoolQuery;
    }

    private function getAllowedWorkspaceIds(string $userId, array $groupIds): array
    {
        return $this->em->getRepository(Workspace::class)->getAllowedWorkspaceIds($userId, $groupIds);
    }

    /**
     * @required
     */
    public function setEm(EntityManagerInterface $em): void
    {
        $this->em = $em;
    }
}
