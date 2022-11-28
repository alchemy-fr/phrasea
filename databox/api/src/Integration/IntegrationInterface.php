<?php

declare(strict_types=1);

namespace App\Integration;

use App\Entity\Integration\WorkspaceIntegration;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

interface IntegrationInterface
{
    public static function getName(): string;
    public static function getTitle(): string;

    public function getConfiguration(): ?TreeBuilder;

    public function validateConfiguration(array $config): void;

    public function getConfigurationInfo(array $options): array;

    public function resolveClientOptions(WorkspaceIntegration $workspaceIntegration, array $options): array;
}
