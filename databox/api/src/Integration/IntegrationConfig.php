<?php

declare(strict_types=1);

namespace App\Integration;

use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Env\EnvResolver;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * The processed configuration of an integration, whose string values of the first-level
 * keys resolve their `${NAME}` (or `$NAME`) references through EnvResolver, in a single
 * pass: a resolved value is never resolved again.
 *
 * In a workspace integration, the instance variables (`PWI_<NAME>`) are shared by every
 * workspace: they must never leave for a host chosen by a workspace editor. So a URL key
 * (ConfigWidget::ATTR_URL) set to another value than its default resolves no reference,
 * and the instance variables no longer resolve as soon as one of the URLs is not the
 * default, or depends on a secret or a variable of the workspace.
 */
class IntegrationConfig implements \ArrayAccess
{
    private const string REFERENCE_PATTERN = '#\$(?:\{([^}]+)}|([A-Z\d_]+))#i';

    private array $cache = [];
    private ?bool $instanceEnvAllowed = null;

    /**
     * @param array<string, string|null> $urlDefaults the default value of each URL key, null when it has none
     */
    public function __construct(
        private readonly array $config,
        private readonly WorkspaceIntegration $workspaceIntegration,
        private readonly IntegrationInterface $integration,
        private readonly EnvResolver $envResolver,
        private readonly array $urlDefaults = [],
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

        if (is_string($value) && str_contains($value, '$') && !$this->isEditorUrl((string) $offset)) {
            // A default URL is an endpoint of the instance
            $allowInstance = array_key_exists($offset, $this->urlDefaults) || $this->isInstanceEnvAllowed();
            $value = preg_replace_callback(
                self::REFERENCE_PATTERN,
                fn (array $match): string => $this->envResolver->getEnv(
                    $this->getWorkspaceId(),
                    self::getReferenceName($match),
                    $allowInstance,
                ),
                $value
            );
        }

        return $this->cache[$offset] = $value;
    }

    /**
     * @throws InvalidConfigurationException when a URL set by a workspace editor holds a reference
     */
    public function validateUrls(): void
    {
        foreach (array_keys($this->urlDefaults) as $key) {
            if ($this->isEditorUrl($key) && str_contains((string) $this->config[$key], '$')) {
                $e = new InvalidConfigurationException(sprintf('"%s" is a URL: it cannot refer to a secret or a variable ("$"), write it in full', $key));
                $e->setPath('root.'.$key);

                throw $e;
            }
        }
    }

    /**
     * A URL of a workspace integration set to another value than its default.
     */
    private function isEditorUrl(string $key): bool
    {
        return null !== $this->getWorkspaceId()
            && array_key_exists($key, $this->urlDefaults)
            && isset($this->config[$key])
            && $this->config[$key] !== $this->urlDefaults[$key];
    }

    private function isInstanceEnvAllowed(): bool
    {
        if (null !== $this->instanceEnvAllowed) {
            return $this->instanceEnvAllowed;
        }

        $workspaceId = $this->getWorkspaceId();
        if (null === $workspaceId) {
            return $this->instanceEnvAllowed = true;
        }

        foreach ($this->urlDefaults as $key => $default) {
            if (!isset($this->config[$key])) {
                continue;
            }
            if ($this->isEditorUrl($key)) {
                return $this->instanceEnvAllowed = false;
            }
            // A secret or a variable of the workspace would redirect the default URL
            preg_match_all(self::REFERENCE_PATTERN, (string) $default, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                if ($this->envResolver->isDefinedInWorkspace($workspaceId, self::getReferenceName($match))) {
                    return $this->instanceEnvAllowed = false;
                }
            }
        }

        return $this->instanceEnvAllowed = true;
    }

    /**
     * @param array<int, string> $match a match of REFERENCE_PATTERN
     */
    private static function getReferenceName(array $match): string
    {
        return '' !== ($match[1] ?? '') ? $match[1] : $match[2];
    }
}
