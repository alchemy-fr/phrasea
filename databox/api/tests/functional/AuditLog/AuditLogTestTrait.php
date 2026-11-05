<?php

declare(strict_types=1);

namespace App\Tests\Functional\AuditLog;

use Alchemy\AuthBundle\Security\JwtInterface;
use Alchemy\AuthBundle\Security\Token\JwtToken;
use App\Entity\Log\AuditLog;
use App\Model\AuditLogActionEnum;

trait AuditLogTestTrait
{
    /**
     * @return AuditLog[] oldest first
     */
    private function findLogs(?AuditLogActionEnum $action = null, ?string $objectId = null): array
    {
        $em = self::getEntityManager();
        $em->clear();

        $criteria = [];
        if (null !== $action) {
            $criteria['action'] = $action;
        }
        if (null !== $objectId) {
            $criteria['objectId'] = $objectId;
        }

        $logs = $em->getRepository(AuditLog::class)->findBy($criteria);
        usort($logs, static fn (AuditLog $a, AuditLog $b): int => $a->getDate() <=> $b->getDate());

        return $logs;
    }

    private function findLog(AuditLogActionEnum $action, ?string $objectId = null): AuditLog
    {
        $logs = $this->findLogs($action, $objectId);
        $this->assertCount(1, $logs, sprintf('One "%s" entry expected for "%s"', $action->getKey(), $objectId ?? 'any object'));

        return $logs[0];
    }

    private function purgeLogs(): void
    {
        $em = self::getEntityManager();
        $em->createQueryBuilder()->delete(AuditLog::class, 'l')->getQuery()->execute();
        $em->clear();
    }

    private function authenticate(JwtInterface $user): void
    {
        $token = new JwtToken('test', $user->getRoles());
        $token->setUser($user);
        self::getService('security.token_storage')->setToken($token);
    }
}
