<?php

declare(strict_types=1);

namespace App\Integration;

use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

interface IntegrationInterface
{
    public static function getName(): string;

    public static function requiresWorkspace(): bool;

    public static function getDisplayName(): string;

    public function buildConfiguration(NodeBuilder $builder): void;

    public function generateConfigurationDefaults(array $userConfig): array;

    public function validateConfiguration(IntegrationConfig $config): void;

    // Normalize configuration to be persisted to database
    public function normalizeConfiguration(array $config, ?Workspace $workspace): array;

    public function denormalizeConfiguration(array $config, ?Workspace $workspace): array;

    public function getConfigurationInfo(IntegrationConfig $config): array;

    /**
     * @return array The exposed configuration through API
     */
    public function resolveClientConfiguration(WorkspaceIntegration $workspaceIntegration, IntegrationConfig $config): array;

    /**
     * This is used to filter integrations from client.
     *
     * @return string[]
     */
    public function getSupportedContexts(): array;
}
