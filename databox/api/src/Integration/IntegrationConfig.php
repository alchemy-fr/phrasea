<?php

namespace App\Integration;

use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Env\EnvResolver;

class IntegrationConfig implements \ArrayAccess
{
    private array $cache = [];

    public function __construct(
        private readonly array $config,
        private readonly WorkspaceIntegration $workspaceIntegration,
        private readonly IntegrationInterface $integration,
        private readonly EnvResolver $envResolver,
    ) {
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \InvalidArgumentException('Read only configuration');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \InvalidArgumentException('Read only configuration');
    }

    public function getIntegration(): IntegrationInterface
    {
        return $this->integration;
    }

    public function getWorkspaceIntegration(): WorkspaceIntegration
    {
        return $this->workspaceIntegration;
    }

    public function getWorkspaceId(): ?string
    {
        return $this->workspaceIntegration->getWorkspaceId();
    }

    public function getIntegrationId(): string
    {
        return $this->workspaceIntegration->getId();
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->cache[$offset]) || isset($this->config[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        if (isset($this->cache[$offset])) {
            return $this->cache[$offset];
        }

        if (!isset($this->config[$offset])) {
            return null;
        }

        $value = $this->config[$offset];

        if (is_string($value) && str_contains($value, '$')) {
            $value = preg_replace_callback(
                '#\$\{([^}]+)}#i',
                fn (array $match): string => $this->resolve($match),
                $value
            );
            $value = preg_replace_callback(
                '#\$([A-Z\d_]+)#i',
                fn (array $match): string => $this->resolve($match),
                $value
            );
        }

        return $this->cache[$offset] = $value;
    }

    private function resolve(array $match): string
    {
        return $this->envResolver->getEnv($this->workspaceIntegration->getWorkspaceId(), $match[1]);
    }
}
