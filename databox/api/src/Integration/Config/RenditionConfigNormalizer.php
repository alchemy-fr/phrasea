<?php

declare(strict_types=1);

namespace App\Integration\Config;

use App\Entity\Core\Workspace;
use App\Service\Storage\RenditionManager;
use Ramsey\Uuid\Uuid;

/**
 * Integrations reference renditions by name in their YAML configuration (user-facing),
 * but persist them as rendition definition IDs so that renaming a definition does not
 * break the integration. This service converts both ways for the given config paths.
 */
final readonly class RenditionConfigNormalizer
{
    public function __construct(
        private RenditionManager $renditionManager,
    ) {
    }

    /**
     * Rendition names -> IDs.
     *
     * @param string[] $paths dot-separated paths of the config values holding a rendition name (or a list of names)
     */
    public function normalize(array $config, ?Workspace $workspace, array $paths): array
    {
        return $this->transform($config, $paths, function (string $rendition) use ($workspace): string {
            if (Uuid::isValid($rendition)) {
                return $rendition;
            }

            return $this->renditionManager->getRenditionDefinitionByName($this->getWorkspaceId($workspace), $rendition)->getId();
        });
    }

    /**
     * Rendition IDs -> names.
     *
     * @param string[] $paths
     */
    public function denormalize(array $config, ?Workspace $workspace, array $paths): array
    {
        return $this->transform($config, $paths, function (string $rendition) use ($workspace): string {
            if (!Uuid::isValid($rendition)) {
                return $rendition;
            }

            try {
                return $this->renditionManager->getRenditionDefinitionById($this->getWorkspaceId($workspace), $rendition)->getName();
            } catch (\InvalidArgumentException) {
                // Definition removed since: keep the ID so that the user sees what is configured
                return $rendition;
            }
        });
    }

    private function getWorkspaceId(?Workspace $workspace): string
    {
        return $workspace?->getId() ?? throw new \LogicException('A workspace is required to resolve renditions');
    }

    /**
     * @param string[] $paths
     */
    private function transform(array $config, array $paths, \Closure $callback): array
    {
        foreach ($paths as $path) {
            $config = $this->transformPath($config, explode('.', $path), $callback);
        }

        return $config;
    }

    private function transformPath(array $config, array $segments, \Closure $callback): array
    {
        $key = array_shift($segments);
        if (!array_key_exists($key, $config)) {
            return $config;
        }

        if (!empty($segments)) {
            if (is_array($config[$key])) {
                $config[$key] = $this->transformPath($config[$key], $segments, $callback);
            }

            return $config;
        }

        $value = $config[$key];
        if (is_array($value)) {
            $config[$key] = array_map(fn ($v) => is_string($v) && '' !== $v ? $callback($v) : $v, $value);
        } elseif (is_string($value) && '' !== $value) {
            $config[$key] = $callback($value);
        }

        return $config;
    }
}
