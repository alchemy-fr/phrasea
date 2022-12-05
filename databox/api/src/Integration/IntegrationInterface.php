<?php

declare(strict_types=1);

namespace App\Integration;

use App\Entity\Integration\WorkspaceIntegration;
use Symfony\Component\OptionsResolver\OptionsResolver;

interface IntegrationInterface
{
    public static function getName(): string;
    public static function getTitle(): string;

    public function configureOptions(OptionsResolver $resolver): void;

    public function getConfigurationInfo(array $options): array;

    public function resolveClientOptions(WorkspaceIntegration $workspaceIntegration, array $options): array;
}
