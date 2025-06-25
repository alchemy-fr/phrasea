<?php

declare(strict_types=1);

namespace App\Integration;

use Alchemy\Workflow\Model\Job;

/**
 * Add support for filtering job needs in an integration.
 */
interface FilterNeedIntegrationInterface extends IntegrationInterface
{
    /**
     * Get the list of jobs that are needed for this integration.
     * Return null if all jobs are needed.
     */
    public function getNeededJobs(IntegrationConfig $config, IntegrationConfig $neededIntegrationConfig, Job $job): ?array;
}
