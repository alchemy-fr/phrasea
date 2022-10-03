<?php

declare(strict_types=1);

namespace App\Integration;

use App\Entity\Integration\WorkspaceIntegration;
use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class AbstractIntegration implements IntegrationInterface
{
    public function configureOptions(OptionsResolver $resolver): void
    {
    }

    public function getConfigurationInfo(array $options): array
    {
        return [];
    }

    public function resolveClientOptions(WorkspaceIntegration $workspaceIntegration, array $options): array
    {
        return [];
    }
}
