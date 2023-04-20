<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Tests\State;

use Alchemy\Workflow\Date\MicroDateTime;
use Alchemy\Workflow\Event\WorkflowEvent;
use Alchemy\Workflow\State\Repository\MemoryStateRepository;
use Alchemy\Workflow\State\WorkflowState;
use PHPUnit\Framework\TestCase;

class SerializationStateTest extends TestCase
{
    public function testWorkflowStateSerialization(): void
    {
        $stateRepository = new MemoryStateRepository();
        $state = new WorkflowState($stateRepository, 'foo', new WorkflowEvent('event', [
            'foo' => 'bar',
            'int' => 1
        ]), '42');

        $unserialized = unserialize(serialize($state));
        $this->assertInstanceOf(WorkflowState::class, $unserialized);

        $this->assertEquals('42', $unserialized->getId());
        $this->assertEquals($state->getStartedAt(), $unserialized->getStartedAt());
        $this->assertInstanceOf(MicroDateTime::class, $unserialized->getStartedAt());
        $this->assertEquals($state->getEvent()->getName(), 'event');
        $this->assertEquals($state->getEvent()->getInputs()->getArrayCopy(), [
            'foo' => 'bar',
            'int' => 1
        ]);
        $this->assertNull($unserialized->getEndedAt());

        $state->setEndedAt(new MicroDateTime());

        $unserialized = unserialize(serialize($state));
        $this->assertInstanceOf(WorkflowState::class, $unserialized);
        $this->assertEquals($state->getStartedAt(), $unserialized->getStartedAt());
        $this->assertInstanceOf(MicroDateTime::class, $unserialized->getStartedAt());
        $this->assertInstanceOf(MicroDateTime::class, $unserialized->getEndedAt());
        $this->assertEquals($state->getEndedAt(), $unserialized->getEndedAt());
    }
}
