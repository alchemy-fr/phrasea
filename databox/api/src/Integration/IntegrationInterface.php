<?php

declare(strict_types=1);

namespace App\Integration;

use App\Entity\Integration\WorkspaceIntegration;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

interface IntegrationInterface
{
    public static function getName(): string;

    public static function requiresWorkspace(): bool;

    public static function getTitle(): string;

    public function buildConfiguration(NodeBuilder $builder): void;

    public function validateConfiguration(IntegrationConfig $config): void;

    public function getConfigurationInfo(IntegrationConfig $config): array;

    public function resolveClientConfiguration(WorkspaceIntegration $workspaceIntegration, IntegrationConfig $config): array;

    /**
     * This is used to filter integrations from client.
     *
     * @return string[]
     */
    public function getSupportedContexts(): array;
}
