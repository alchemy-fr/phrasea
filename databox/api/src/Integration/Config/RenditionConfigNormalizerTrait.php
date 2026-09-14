<?php

declare(strict_types=1);

namespace App\Integration\Config;

use App\Entity\Core\Workspace;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * For integrations whose configuration references renditions: stores rendition definition IDs,
 * exposes rendition names. Implement getRenditionConfigPaths() to declare the config paths.
 */
trait RenditionConfigNormalizerTrait
{
    private RenditionConfigNormalizer $renditionConfigNormalizer;

    #[Required]
    public function setRenditionConfigNormalizer(RenditionConfigNormalizer $renditionConfigNormalizer): void
    {
        $this->renditionConfigNormalizer = $renditionConfigNormalizer;
    }

    /**
     * @return string[] dot-separated config paths holding a rendition name or a list of rendition names
     */
    abstract protected function getRenditionConfigPaths(): array;

    public function normalizeConfiguration(array $config, ?Workspace $workspace): array
    {
        return $this->renditionConfigNormalizer->normalize($config, $workspace, $this->getRenditionConfigPaths());
    }

    public function denormalizeConfiguration(array $config, ?Workspace $workspace): array
    {
        return $this->renditionConfigNormalizer->denormalize($config, $workspace, $this->getRenditionConfigPaths());
    }
}
