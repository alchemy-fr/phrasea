<?php

declare(strict_types=1);

namespace App\Integration;

use Alchemy\Workflow\Model\Job;
use Alchemy\Workflow\Model\Step;

abstract class WorkflowHelper
{
    public static function createIntegrationJob(
        IntegrationConfig $config,
        string $action,
        ?string $idSuffix = null,
        ?string $nameSuffix = null,
        array $metadata = [],
    ): Job {
        $workspaceIntegration = $config->getWorkspaceIntegration();
        $integration = $config->getIntegration();
        $id = self::getJobIdPrefix($config);
        if (!empty($idSuffix)) {
            $id .= ':'.$idSuffix;
        }
        $name = $workspaceIntegration->getTitle() ?: $integration::getTitle();
        if (!empty($nameSuffix)) {
            $name .= ' - '.$nameSuffix;
        }

        $job = new Job($id);
        $job->setMetadata($metadata);
        $job->setName($name);
        if (is_subclass_of($action, IfActionInterface::class)) {
            $job->setIf($action.'::evaluateIf');
        }
        $job->getWith()['integrationId'] = $workspaceIntegration->getId();

        $step = new Step('_', $name);
        $step->setUses($action);

        $job->getSteps()->append($step);

        return $job;
    }

    public static function getJobIdPrefix(IntegrationConfig $config): string
    {
        return $config->getIntegration()::getName().':'.$config->getWorkspaceIntegration()->getId();
    }
}
