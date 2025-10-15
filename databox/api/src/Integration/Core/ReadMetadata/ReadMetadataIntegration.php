<?php

declare(strict_types=1);

namespace App\Integration\Core\ReadMetadata;

use Alchemy\Workflow\Model\Workflow;
use App\Integration\AbstractIntegration;
use App\Integration\IntegrationConfig;
use App\Integration\WorkflowHelper;
use App\Integration\WorkflowIntegrationInterface;
use App\Service\Workflow\Action\InitializeAttributesAction;
use App\Service\Workflow\Action\ReadMetadataAction;
use App\Service\Workflow\Event\AssetIngestWorkflowEvent;

class ReadMetadataIntegration extends AbstractIntegration implements WorkflowIntegrationInterface
{
    public function getWorkflowJobDefinitions(IntegrationConfig $config, Workflow $workflow): iterable
    {
        if (!$workflow->getOn()->hasEventName(AssetIngestWorkflowEvent::EVENT)) {
            return [];
        }

        $readMetadataJob = WorkflowHelper::createIntegrationJob(
            $config,
            ReadMetadataAction::class,
            'extract',
            'Extract file metadata',
        );

        $initializeAttributesJob = WorkflowHelper::createIntegrationJob(
            $config,
            InitializeAttributesAction::class,
            'initialize_attributes',
            'Initialize Attributes',
        );
        $initializeAttributesJob->getNeeds()->append($readMetadataJob->getId());

        yield $readMetadataJob;
        yield $initializeAttributesJob;
    }

    public static function getTitle(): string
    {
        return 'Read Metadata';
    }

    public static function getName(): string
    {
        return 'core.read_metadata';
    }
}
