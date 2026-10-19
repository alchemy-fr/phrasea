<?php

declare(strict_types=1);

namespace App\Tests\Functional\Automation;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Automation\Action\TagsAction;
use App\Automation\Schedule\AutomationScheduler;
use App\Consumer\Handler\Automation\RunAutomation;
use App\Entity\Automation\AutomationRun;
use App\Model\AutomationRunStatusEnum;
use App\Model\AutomationTriggerEnum;
use App\Tests\Functional\AbstractDataboxTestCase;

final class DispatchDueAutomationsTest extends AbstractDataboxTestCase
{
    use AutomationTestTrait;

    public function testOnlyDueEnabledAutomationsAreTriggered(): void
    {
        $savedSearch = $this->createSavedSearchWith(KeycloakClientTestMock::USER_UID, ['query' => 'cats']);
        $actions = [['type' => TagsAction::TYPE, 'config' => ['add' => [], 'remove' => []]]];
        $now = new \DateTimeImmutable('2026-03-10 12:00:00');
        $past = $now->modify('-1 minute');

        $due = $this->createAutomationEntity($savedSearch, $actions, options: [
            'name' => 'Due',
            'schedule' => '*/15 * * * *',
            'nextRunAt' => $past,
        ]);
        $notDue = $this->createAutomationEntity($savedSearch, $actions, options: [
            'name' => 'Not due',
            'schedule' => '*/15 * * * *',
            'nextRunAt' => $now->modify('+1 minute'),
        ]);
        $disabled = $this->createAutomationEntity($savedSearch, $actions, options: [
            'name' => 'Disabled',
            'schedule' => '*/15 * * * *',
            'nextRunAt' => $past,
            'enabled' => false,
        ]);
        $manual = $this->createAutomationEntity($savedSearch, $actions, options: [
            'name' => 'Manual',
            'nextRunAt' => $past,
        ]);

        $task = self::interceptTransport('task');

        $triggered = self::getService(AutomationScheduler::class)->dispatchDue($now);
        $this->assertSame([$due->getId()], array_map(fn ($a): string => $a->getId(), $triggered));

        $this->assertEquals(new \DateTimeImmutable('2026-03-10 12:15:00'), $this->findAutomation($due->getId())->getNextRunAt());
        $this->assertEquals($now->modify('+1 minute'), $this->findAutomation($notDue->getId())->getNextRunAt());
        $this->assertEquals($past, $this->findAutomation($disabled->getId())->getNextRunAt());
        $this->assertEquals($past, $this->findAutomation($manual->getId())->getNextRunAt());

        $runs = self::getEntityManager()->getRepository(AutomationRun::class)->findAll();
        $this->assertCount(1, $runs);
        $this->assertSame($due->getId(), $runs[0]->getAutomation()->getId());
        $this->assertSame(AutomationTriggerEnum::Schedule, $runs[0]->getTrigger());
        $this->assertSame(AutomationRunStatusEnum::Pending, $runs[0]->getStatus());
        $this->assertNull($runs[0]->getInitiatorId());

        $sent = array_map(fn ($envelope): object => $envelope->getMessage(), $task->getSent());
        $this->assertEquals([new RunAutomation($runs[0]->getId())], $sent);

        // Moved forward: not due any more
        $this->assertSame([], self::getService(AutomationScheduler::class)->dispatchDue($now));
    }
}
