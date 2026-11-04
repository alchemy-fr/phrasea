<?php

declare(strict_types=1);

namespace App\Service\Asset;

use Alchemy\RenditionFactory\Config\YamlLoader;
use Alchemy\RenditionFactory\DTO\BuildConfig\BuildConfig;
use App\Entity\Core\RenditionDefinition;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Parses the build definitions, once per definition content.
 */
final class RenditionDefinitionParser implements ResetInterface
{
    /**
     * @var array<string, BuildConfig|null>
     */
    private array $cache = [];

    public function __construct(
        private readonly YamlLoader $loader,
    ) {
    }

    /**
     * @return BuildConfig|null null when the definition is not a valid custom build
     */
    public function getBuildConfig(RenditionDefinition $definition): ?BuildConfig
    {
        if (RenditionDefinition::BUILD_MODE_CUSTOM !== $definition->getBuildMode()) {
            return null;
        }

        $content = $definition->getDefinition();
        if (empty($content)) {
            return null;
        }

        $key = $definition->getId().'|'.md5($content);
        if (!array_key_exists($key, $this->cache)) {
            try {
                $this->cache[$key] = $this->loader->parse($content);
            } catch (\InvalidArgumentException|ParseException) {
                $this->cache[$key] = null;
            }
        }

        return $this->cache[$key];
    }

    /**
     * Whether the definition builds its rendition without source file ("generate:").
     */
    public function isGenerateDefinition(RenditionDefinition $definition): bool
    {
        return null !== $this->getBuildConfig($definition)?->getGenerate();
    }

    public function reset(): void
    {
        $this->cache = [];
    }
}
