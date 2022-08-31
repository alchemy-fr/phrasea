<?php

declare(strict_types=1);

namespace App\Integration;

use InvalidArgumentException;

class IntegrationRegistry
{
    /**
     * @var IntegrationInterface[]
     */
    private array $integrations;

    public function __construct(iterable $integrations)
    {
        $this->integrations = $integrations instanceof \Traversable ? iterator_to_array($integrations) : $integrations;
    }

    public function getIntegration(string $type): ?IntegrationInterface
    {
        return $this->integrations[$type] ?? null;
    }

    public function getStrictIntegration(string $integration): ?IntegrationInterface
    {
        if (!isset($this->integrations[$integration])) {
            throw new InvalidArgumentException(sprintf('Integration "%s" not found', $integration));
        }

        return $this->getIntegration($integration);
    }

    /**
     * @return IntegrationInterface[]
     */
    public function getIntegrations(): array
    {
        return $this->integrations;
    }
}
