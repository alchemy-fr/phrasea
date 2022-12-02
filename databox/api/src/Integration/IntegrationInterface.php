<?php

declare(strict_types=1);

namespace App\Integration;

use App\Entity\Integration\WorkspaceIntegration;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

interface IntegrationInterface
{
    public static function getName(): string;

    public static function getTitle(): string;

    public function buildConfiguration(NodeBuilder $builder): void;

    public function validateConfiguration(array $config): void;

    public function getConfigurationInfo(array $config): array;

    public function resolveClientConfiguration(WorkspaceIntegration $workspaceIntegration, array $config): array;
}
