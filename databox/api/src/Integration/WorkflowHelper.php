<?php

declare(strict_types=1);

namespace App\Integration;

use Alchemy\Workflow\Model\Job;
use Alchemy\Workflow\Model\Step;
use App\Entity\Integration\WorkspaceIntegration;

abstract class WorkflowHelper
{
    public static function createIntegrationJob(
        array $config,
        string $action,
        ?string $idSuffix = null,
        ?string $nameSuffix = null,
    ): Job {
        /** @var WorkspaceIntegration $workspaceIntegration */
        $workspaceIntegration = $config['workspaceIntegration'];
        /** @var IntegrationInterface $integration */
        $integration = $config['integration'];
        $id = $integration::getName().':'.$workspaceIntegration->getId();
        if (!empty($idSuffix)) {
            $id .= ':'.$idSuffix;
        }
        $name = $workspaceIntegration->getTitle() ?? $integration::getTitle();
        if (!empty($nameSuffix)) {
            $name .= ' - '.$nameSuffix;
        }

        $job = new Job($id);
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
}
