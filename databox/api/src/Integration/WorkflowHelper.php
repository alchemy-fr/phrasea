<?php

declare(strict_types=1);

namespace App\Integration;

use Alchemy\Workflow\Model\Job;
use Alchemy\Workflow\Model\Step;

abstract class WorkflowHelper
{
    public static function createIntegrationJob(
        string $name,
        string $title,
        array $config,
        string $action,
    ): Job
    {
        $job = new Job($name);
        if (is_subclass_of($action, IfActionInterface::class)) {
            $job->setIf($action.'::shouldRun');
        }
        $job->setWith([
            'integrationId' => $config['integrationId'],
        ]);

        $step = new Step($name, $title);
        $step->setUses($action);

        $job->getSteps()->append($step);

        return $job;
    }
}
