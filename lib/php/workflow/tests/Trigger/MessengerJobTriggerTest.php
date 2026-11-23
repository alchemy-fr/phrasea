<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Tests\Trigger;

use Alchemy\Workflow\Message\JobConsumer;
use Alchemy\Workflow\Trigger\JobTrigger;
use Alchemy\Workflow\Trigger\MessengerJobTrigger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

class MessengerJobTriggerTest extends TestCase
{
    public function testJobWithoutQueueKeepsTheDefaultRouting(): void
    {
        $envelope = $this->trigger(new JobTrigger('wf', 'job', 'state'));

        $message = $envelope->getMessage();
        $this->assertInstanceOf(JobConsumer::class, $message);
        $this->assertSame('state', $message->getJobStateId());
        $this->assertNull($envelope->last(TransportNamesStamp::class));
    }

    public function testJobQueueIsTheTransportName(): void
    {
        $envelope = $this->trigger(new JobTrigger('wf', 'job', 'state', 'heavy'));

        $this->assertSame(['heavy'], $envelope->last(TransportNamesStamp::class)?->getTransportNames());
    }

    private function trigger(JobTrigger $jobTrigger): Envelope
    {
        $bus = new class implements MessageBusInterface {
            public ?Envelope $envelope = null;

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                return $this->envelope = Envelope::wrap($message, $stamps);
            }
        };

        new MessengerJobTrigger($bus)->triggerJob($jobTrigger);

        $this->assertNotNull($bus->envelope);

        return $bus->envelope;
    }
}
