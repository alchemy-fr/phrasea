<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Tests;

use Alchemy\Workflow\Event\WorkflowEvent;
use Alchemy\Workflow\Exception\InvalidEventException;
use Symfony\Component\Console\Output\BufferedOutput;

class EventInputValidatorTest extends AbstractWorkflowTest
{
    /**
     * @dataProvider getCases
     */
    public function testEventInputsAreValidated(string $eventName, array $args, ?string $expectedError): void
    {
        $output = new BufferedOutput();
        [$orchestrator] = $this->createOrchestrator([
            'events.yaml',
        ], null, $output);

        if (null !== $expectedError) {
            $this->expectException(InvalidEventException::class);
            $this->expectExceptionMessage($expectedError);
        }
        $i = $orchestrator->dispatchEvent(new WorkflowEvent($eventName, $args));

        if (null === $expectedError) {
            $this->assertEquals(1, $i);
        }
    }

    public function getCases(): array
    {
        return [
            [
                'copy',
                ['source' => 'a', 'target' => 'b'],
                null,
            ],
            [
                'copy',
                [],
                'Input "source" is required for event "copy"',
            ],
            [
                'copy',
                ['source' => 'a'],
                'Input "target" is required for event "copy"',
            ],
            [
                'copy',
                ['target' => 'a'],
                'Input "source" is required for event "copy"',
            ],
            [
                'copy',
                ['source' => 1],
                'Input "source" must be type of string for event "copy" (int given)',
            ],
            [
                'touch',
                [],
                'Input "path" is required for event "touch"',
            ],
            [
                'touch',
                ['extra' => 'param'],
                'Input "path" is required for event "touch"',
            ],
            [
                'touch',
                ['extra' => 'param', 'path' => '/a/b/c'],
                null,
            ],
            [
                'echo',
                [],
                null,
            ],
            [
                'echo',
                ['text' => 'foo'],
                null,
            ],
            [
                'echo',
                ['text' => 1],
                'Input "text" must be type of string for event "echo" (int given)',
            ],
            [
                'echo',
                ['text' => true],
                'Input "text" must be type of string for event "echo" (bool given)',
            ],
            [
                'empty',
                [],
                null,
            ],
            [
                'empty',
                ['foo' => 'bar'],
                null,
            ],
        ];
    }
}
