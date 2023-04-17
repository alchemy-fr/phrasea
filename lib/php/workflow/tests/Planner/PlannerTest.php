<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Tests\Planner;

use Alchemy\Workflow\Planner\Stage;
use Alchemy\Workflow\Tests\AbstractWorkflowTest;

class PlannerTest extends AbstractWorkflowTest
{
    public function testBuildStages(): void
    {
        $planner = $this->createPlanner([
            'file-manipulator.yaml',
            'echoer.yaml',
        ]);
        $plan = $planner->planAll();

        $this->assertCount(3, $plan->getStages());

        /** @var Stage[] $stages */
        $stages = $plan->getStages()->getArrayCopy();
        $stage = array_shift($stages);
        $this->assertCount(4, $stage->getRuns());
        $stage = array_shift($stages);
        $this->assertCount(2, $stage->getRuns());
        $stage = array_shift($stages);
        $this->assertCount(1, $stage->getRuns());
    }

    public function testRecursiveNeedsWillThrowError(): void
    {
        $planner = $this->createPlanner([
            'recursive-needs.yaml',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to build stage 1: empty runs. Please check you don\'t have circular needs');
        $planner->planAll();
    }
}
