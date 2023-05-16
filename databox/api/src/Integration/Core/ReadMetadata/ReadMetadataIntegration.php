<?php

declare(strict_types=1);

namespace App\Integration\Core\ReadMetadata;

use App\Integration\AbstractIntegration;
use App\Integration\WorkflowHelper;
use App\Integration\WorkflowIntegrationInterface;
use App\Workflow\Action\InitializeAttributesAction;
use App\Workflow\Action\ReadMetadataAction;

class ReadMetadataIntegration extends AbstractIntegration implements WorkflowIntegrationInterface
{
    public function getWorkflowJobDefinitions(array $config): iterable
    {
        $readMetadataJob = WorkflowHelper::createIntegrationJob(
            self::getName(),
            self::getTitle(),
            $config,
            ReadMetadataAction::class,
        );

        $initializeAttributesJob = WorkflowHelper::createIntegrationJob(
            'core.initialize_attributes',
            'Initialize Attributes',
            $config,
            InitializeAttributesAction::class,
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
