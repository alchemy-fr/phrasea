<?php

declare(strict_types=1);

namespace App\Repository\Automation;

use App\Entity\Automation\AutomationShare;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AutomationShare>
 */
class AutomationShareRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AutomationShare::class);
    }

    public function findOneByAutomationAndWorkspace(string $automationId, string $workspaceId): ?AutomationShare
    {
        return $this->findOneBy([
            'automation' => $automationId,
            'workspace' => $workspaceId,
        ]);
    }
}
