<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Tests\Dumper;

use Alchemy\Workflow\Dumper\JsonWorkflowDumper;
use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\WorkflowState;
use Symfony\Component\Console\Output\BufferedOutput;

class JsonDumperTest extends AbstractDumperTest
{
    public function testJsonDumper(): void
    {
        $workflowState = $this->createWorkflowState('42');

        $planner = $this->createPlanner([
            'echoer.yaml',
        ]);
        $plan = $planner->planAll();

        $output = new BufferedOutput();
        (new JsonWorkflowDumper())->dumpWorkflow($workflowState, $plan, $output);

        $out = json_decode($output->fetch(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals([
            'id' => '42',
            'name' => 'foo',
            'status' => WorkflowState::STATUS_STARTED,
            'startedAt' => $out['startedAt'],
            'endedAt' => null,
            'stages' => [
                [
                    'stage' => 1,
                    'jobs' => [
                        [
                            'jobId' => 'intro',
                            'name' => 'intro',
                            'status' => JobState::STATUS_SUCCESS,
                            'startedAt' => '2000-05-12T12:12:42.424242+00:00',
                            'endedAt' => '2000-05-12T12:12:43.424242+00:00',
                            'triggeredAt' => $out['stages'][0]['jobs'][0]['triggeredAt'],
                            'outputs' => [],
                            'duration' => '1.000s',
                            'needs' => [],
                            'with' => [],
                            'disabled' => false,
                            'stateId' => 'intro-0',
                            'number' => 0,
            ],
                        [
                            'jobId' => 'never-called',
                            'name' => 'never-called',
                            'status' => JobState::STATUS_SKIPPED,
                            'outputs' => [],
                            'duration' => '-',
                            'needs' => [],
                            'triggeredAt' => $out['stages'][0]['jobs'][1]['triggeredAt'],
                            'if' => 'env.WF_TEST == "bar"',
                            'with' => [],
                            'disabled' => false,
                            'stateId' => 'never-called-0',
                            'number' => 0,
                        ],
                    ],
                ],
                [
                    'stage' => 2,
                    'jobs' => [
                        [
                            'jobId' => 'content',
                            'name' => 'content',
                            'status' => JobState::STATUS_RUNNING,
                            'startedAt' => '2000-05-12T12:12:44.424242+00:00',
                            'triggeredAt' => $out['stages'][1]['jobs'][0]['triggeredAt'],
                            'outputs' => [],
                            'duration' => '-',
                            'needs' => [
                                'intro',
                            ],
                            'inputs' => [
                                'foo' => 'bar',
                                'baz' => 42,
                            ],
                            'with' => [],
                            'disabled' => false,
                            'stateId' => 'content-0',
                            'number' => 0,
                        ],
                        [
                            'jobId' => 'content_bis',
                            'name' => 'content_bis',
                            'status' => JobState::STATUS_RUNNING,
                            'startedAt' => '2000-05-12T12:12:44.424242+00:00',
                            'triggeredAt' => $out['stages'][1]['jobs'][1]['triggeredAt'],
                            'outputs' => [],
                            'duration' => '-',
                            'needs' => [
                                'intro',
                            ],
                            'inputs' => [],
                            'with' => [
                                'foo' => 'bar',
                            ],
                            'disabled' => false,
                            'stateId' => 'content_bis-0',
                            'number' => 0,
                        ],
                    ],
                ],
                [
                    'stage' => 3,
                    'jobs' => [
                        [
                            'jobId' => 'outro',
                            'name' => 'outro',
                            'needs' => [
                                'content',
                                'content_bis',
                            ],
                            'with' => [],
                            'disabled' => false,
                        ],
                    ],
                ],
            ],
            'duration' => '-',
            'context' => [],
        ], $out);
    }
}
