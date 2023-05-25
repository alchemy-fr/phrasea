<?php

declare(strict_types=1);

namespace App\Integration;

use Alchemy\Workflow\Model\Job;

interface WorkflowIntegrationInterface
{
    /**
     * @return Job[]
     */
    public function getWorkflowJobDefinitions(array $config): iterable;
}
