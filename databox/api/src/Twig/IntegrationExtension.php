<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\IntegrationManager;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class IntegrationExtension extends AbstractExtension
{
    public function __construct(private readonly IntegrationManager $integrationManager)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_integration_options', $this->getIntegrationOptions(...)),
            new TwigFunction('get_integration_config_info', $this->getIntegrationConfigInfo(...)),
        ];
    }

    public function getIntegrationOptions(WorkspaceIntegration $integration): string
    {
        $options = $this->integrationManager->getIntegrationConfiguration($integration);

        return json_encode($options, JSON_PRETTY_PRINT);
    }

    public function getIntegrationConfigInfo(WorkspaceIntegration $integration): array
    {
        try {
            return $this->integrationManager->getIntegrationConfigInfo($integration);
        } catch (InvalidConfigurationException) {
            return [];
        }
    }
}
