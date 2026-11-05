<?php

declare(strict_types=1);

namespace App\Tests\Functional\AuditLog;

use App\Entity\Log\AuditLog;
use App\Model\AuditActorTypeEnum;
use App\Model\AuditLogActionEnum;
use App\Tests\Functional\AbstractDataboxTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class PurgeAuditLogCommandTest extends AbstractDataboxTestCase
{
    use AuditLogTestTrait;

    public function testEntriesOlderThanTheRetentionAreDeleted(): void
    {
        $old = $this->createLog('-40 days');
        $recent = $this->createLog('-10 days');

        $tester = $this->createTester();
        $this->assertSame(0, $tester->execute(['--days' => '30']));
        $this->assertStringContainsString('1 audit log entries older than 30 days deleted', $tester->getDisplay());

        $ids = array_map(static fn (AuditLog $log): string => $log->getId(), $this->findLogs());
        $this->assertNotContains($old, $ids);
        $this->assertContains($recent, $ids);
    }

    public function testZeroKeepsEverything(): void
    {
        $old = $this->createLog('-4000 days');

        $tester = $this->createTester();
        $this->assertSame(0, $tester->execute(['--days' => '0']));

        $this->assertContains($old, array_map(static fn (AuditLog $log): string => $log->getId(), $this->findLogs()));
    }

    public function testTheDefaultRetentionIsAYear(): void
    {
        $old = $this->createLog('-400 days');
        $recent = $this->createLog('-300 days');

        $this->assertSame(0, $this->createTester()->execute([]));

        $ids = array_map(static fn (AuditLog $log): string => $log->getId(), $this->findLogs());
        $this->assertNotContains($old, $ids);
        $this->assertContains($recent, $ids);
    }

    private function createLog(string $date): string
    {
        $log = new AuditLog(new \DateTimeImmutable($date));
        $log->setAction(AuditLogActionEnum::ObjectUpdated);
        $log->setActorType(AuditActorTypeEnum::System);

        $em = self::getEntityManager();
        $em->persist($log);
        $em->flush();

        return $log->getId();
    }

    private function createTester(): CommandTester
    {
        $application = new Application(self::$kernel);

        return new CommandTester($application->find('app:audit-log:purge'));
    }
}
