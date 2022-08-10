<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Entity\Core\Workspace;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Security\Voter\ChuckNorrisVoter;
use Doctrine\ORM\EntityManagerInterface;
use Elastica\Query;
use Symfony\Component\Security\Core\Security;

abstract class AbstractSearch
{
    private EntityManagerInterface $em;
    protected Security $security;

    public function createACLBoolQuery(?string $userId, array $groupIds): Query\BoolQuery
    {
        $aclBoolQuery = new Query\BoolQuery();

        $shoulds = [];

        if ($this->security->isGranted(ChuckNorrisVoter::ROLE)) {
            return $aclBoolQuery;
        }

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
        } else {
            $shoulds[] = new Query\Range('privacy', [
                'gte' => WorkspaceItemPrivacyInterface::PUBLIC,
            ]);
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

    /**
     * @required
     */
    public function setSecurity(Security $security): void
    {
        $this->security = $security;
    }
}
