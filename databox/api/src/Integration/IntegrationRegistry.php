<?php

declare(strict_types=1);

namespace App\Integration;

use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class IntegrationRegistry
{
    /**
     * @var IntegrationInterface[]
     */
    private array $integrations;

    public function __construct(
        #[TaggedIterator(tag: 'app.integration', defaultIndexMethod: 'getName')]
        iterable $integrations
    )
    {
        $this->integrations = $integrations instanceof \Traversable ? iterator_to_array($integrations) : $integrations;
    }

    public function getIntegration(string $type): ?IntegrationInterface
    {
        return $this->integrations[$type] ?? null;
    }

    public function getStrictIntegration(string $integration): IntegrationInterface
    {
        if (!isset($this->integrations[$integration])) {
            throw new \InvalidArgumentException(sprintf('Integration "%s" not found', $integration));
        }

        return $this->getIntegration($integration);
    }

    /**
     * @return IntegrationInterface[]
     */
    public function getSupportingIntegrations(IntegrationContext $context): array
    {
        $result = [];
        foreach ($this->integrations as $integration) {
            if ($integration instanceof UserActionsIntegrationInterface
                && in_array($context, $integration->getSupportedContexts(), true)
            ) {
                $result[] = $integration;
            }
        }

        return $result;
    }

    /**
     * @return IntegrationInterface[]
     */
    public function getIntegrations(): array
    {
        return $this->integrations;
    }
}
