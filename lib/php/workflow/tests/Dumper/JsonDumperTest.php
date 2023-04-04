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
                            'status' => JobState::STATUS_SUCCESS,
                            'startedAt' => '2000-05-12T12:12:42+00:00',
                            'endedAt' => '2000-05-12T12:12:43+00:00',
                            'outputs' => null,
                        ],
                        [
                            'id' => 'never-called',
                            'status' => JobState::STATUS_SKIPPED,
                            'startedAt' => null,
                            'endedAt' => null,
                            'outputs' => null,
                        ],
                    ],
                ],
                [
                    'stage' => 2,
                    'jobs' => [
                        [
                            'id' => 'content',
                            'status' => JobState::STATUS_RUNNING,
                            'startedAt' => '2000-05-12T12:12:44+00:00',
                            'endedAt' => null,
                            'outputs' => null,
                        ],
                        [
                            'id' => 'content-bis',
                            'status' => JobState::STATUS_RUNNING,
                            'startedAt' => '2000-05-12T12:12:44+00:00',
                            'endedAt' => null,
                            'outputs' => null,
                        ],
                    ],
                ],
                [
                    'stage' => 3,
                    'jobs' => [
                        [
                            'id' => 'outro',
                        ],
                    ],
                ],
            ],
        ], $out);
    }
}
