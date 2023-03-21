<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Planner;


/**
 * Plan contains a list of stages to run in series.
 */
class Plan
{
    private StageList $stages;

    public function __construct(StageList $stages)
    {
        $this->stages = $stages;
    }

    /**
     * @return Stage[]
     */
    public function getStages(): StageList
    {
        return $this->stages;
    }
}
