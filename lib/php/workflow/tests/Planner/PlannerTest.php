<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Tests\Planner;

use Alchemy\Workflow\Loader\YamlLoader;
use Alchemy\Workflow\Planner\Stage;
use Alchemy\Workflow\Planner\WorkflowPlanner;
use PHPUnit\Framework\TestCase;

class PlannerTest extends TestCase
{
    public function testBuildStages(): void
    {
        $loader = new YamlLoader();

        $planner = new WorkflowPlanner([
            $loader->load(__DIR__.'/../fixtures/file-manipulator.yaml'),
            $loader->load(__DIR__.'/../fixtures/echoer.yaml'),
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
}
