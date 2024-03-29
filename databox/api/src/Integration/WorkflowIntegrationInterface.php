<?php

declare(strict_types=1);

namespace App\Integration;

use Alchemy\Workflow\Model\Job;
use Alchemy\Workflow\Model\Workflow;

interface WorkflowIntegrationInterface
{
    /**
     * @return Job[]
     */
    public function getWorkflowJobDefinitions(array $config, Workflow $workflow): iterable;
}
