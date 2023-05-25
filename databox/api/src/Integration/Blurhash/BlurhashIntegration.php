<?php

declare(strict_types=1);

namespace App\Integration\Blurhash;

use App\Integration\AbstractIntegration;
use App\Integration\WorkflowHelper;
use App\Integration\WorkflowIntegrationInterface;

class BlurhashIntegration extends AbstractIntegration implements WorkflowIntegrationInterface
{
    final public const VERSION = '1.0';

    public static function getName(): string
    {
        return 'blurhash';
    }

    public function getWorkflowJobDefinitions(array $config): iterable
    {
        yield WorkflowHelper::createIntegrationJob(
            $config,
            BlurhashAction::class,
        );
    }

    public static function getTitle(): string
    {
        return 'Blurhash';
    }
}
