<?php

declare(strict_types=1);

namespace App\Integration\Core\ReadMetadata;

use Alchemy\Workflow\Model\Workflow;
use App\Integration\AbstractIntegration;
use App\Integration\WorkflowHelper;
use App\Integration\WorkflowIntegrationInterface;
use App\Workflow\Action\InitializeAttributesAction;
use App\Workflow\Action\ReadMetadataAction;

class ReadMetadataIntegration extends AbstractIntegration implements WorkflowIntegrationInterface
{
    public function getWorkflowJobDefinitions(array $config, Workflow $workflow): iterable
    {
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
