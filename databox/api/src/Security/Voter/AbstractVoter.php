<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Core\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

abstract class AbstractVoter extends Voter
{
    const CREATE = 'CREATE';
    const LIST = 'LIST';
    const READ = 'READ';
    const EDIT = 'EDIT';
    const DELETE = 'DELETE';
    const EDIT_PERMISSIONS = 'EDIT_PERMISSIONS';

    protected EntityManagerInterface $em;
    protected Security $security;
    private array $cache = [];

    protected function getAllowedWorkspaceIds(string $userId, array $groupIds): array
    {
        if (isset($this->cache[$userId])) {
            return $this->cache[$userId];
        }

        $workspaceIds = $this->em->getRepository(Workspace::class)->getAllowedWorkspaceIds($userId, $groupIds);

        $this->cache[$userId] = $workspaceIds;

        return $workspaceIds;
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
