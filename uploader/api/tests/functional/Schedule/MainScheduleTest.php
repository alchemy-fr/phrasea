<?php

declare(strict_types=1);

namespace App\Tests\Functional\Schedule;

use App\Schedule\MainSchedule;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\Generator\MessageContext;

/**
 * Guards against a renamed command silently dropping out of the schedule.
 */
final class MainScheduleTest extends KernelTestCase
{
    public function testEveryTaskRunsAnExistingCommand(): void
    {
        self::bootKernel();
        $application = new Application(self::$kernel);

        $schedule = self::getContainer()->get(MainSchedule::class)->getSchedule();

        $commands = [];
        foreach ($schedule->getRecurringMessages() as $recurringMessage) {
            $context = new MessageContext('main', $recurringMessage->getId(), $recurringMessage->getTrigger(), new \DateTimeImmutable());
            foreach ($recurringMessage->getMessages($context) as $message) {
                $this->assertInstanceOf(RunCommandMessage::class, $message);
                $name = explode(' ', $message->input, 2)[0];
                $this->assertSame($name, $application->find($name)->getName());
                $commands[] = $name;
            }
        }

        $this->assertSame(['app:asset:clean-expired'], $commands);
    }
}
