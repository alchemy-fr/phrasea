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

        $out = json_decode($output->fetch(), true);

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
                            'id' => 'intro',
                            'name' => 'intro',
                            'status' => JobState::STATUS_SUCCESS,
                            'startedAt' => '2000-05-12T12:12:42.424242+00:00',
                            'endedAt' => '2000-05-12T12:12:43.424242+00:00',
                            'triggeredAt' => $out['stages'][0]['jobs'][0]['triggeredAt'],
                            'outputs' => [],
                            'duration' => '1.000s',
                            'needs' => [],
                        ],
                        [
                            'id' => 'never-called',
                            'name' => 'never-called',
                            'status' => JobState::STATUS_SKIPPED,
                            'startedAt' => null,
                            'endedAt' => null,
                            'outputs' => [],
                            'duration' => '-',
                            'needs' => [],
                            'triggeredAt' => $out['stages'][0]['jobs'][1]['triggeredAt'],
                        ],
                    ],
                ],
                [
                    'stage' => 2,
                    'jobs' => [
                        [
                            'id' => 'content',
                            'name' => 'content',
                            'status' => JobState::STATUS_RUNNING,
                            'startedAt' => '2000-05-12T12:12:44.424242+00:00',
                            'triggeredAt' => $out['stages'][1]['jobs'][0]['triggeredAt'],
                            'endedAt' => null,
                            'outputs' => [],
                            'duration' => '-',
                            'needs' => [
                                'intro'
                            ],
                        ],
                        [
                            'id' => 'content_bis',
                            'name' => 'content_bis',
                            'status' => JobState::STATUS_RUNNING,
                            'startedAt' => '2000-05-12T12:12:44.424242+00:00',
                            'triggeredAt' => $out['stages'][1]['jobs'][1]['triggeredAt'],
                            'endedAt' => null,
                            'outputs' => [],
                            'duration' => '-',
                            'needs' => [
                                'intro'
                            ],
                        ],
                    ],
                ],
                [
                    'stage' => 3,
                    'jobs' => [
                        [
                            'id' => 'outro',
                            'name' => 'outro',
                            'needs' => [
                                'content',
                                'content_bis',
                            ],
                        ],
                    ],
                ],
            ],
            'duration' => '-',
            'context' => [],
        ], $out);
    }
}
