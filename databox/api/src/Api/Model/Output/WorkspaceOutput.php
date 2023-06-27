<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Api\Model\Output\Traits\CapabilitiesDTOTrait;
use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ApiResource(
 *     shortName="workspace",
 *     normalizationContext="workspace:read",
 * )
 */
class WorkspaceOutput extends AbstractUuidOutput
{
    use CapabilitiesDTOTrait;
    use CreatedAtDTOTrait;

    #[Groups(['workspace:index', 'workspace:read', 'collection:index', 'collection:read'])]
    protected array $capabilities = [];

    #[Groups(['workspace:index', 'workspace:read', 'collection:index', 'collection:read', 'asset:index', 'asset:read', 'Webhook', 'renddef:index'])]
    private string $name;

    #[Groups(['workspace:index', 'workspace:read'])]
    private string $slug;

    #[Groups(['workspace:index', 'workspace:read'])]
    private bool $public;

    #[Groups(['workspace:read'])]
    private ?array $enabledLocales = null;

    #[Groups(['workspace:read'])]
    private ?array $localeFallbacks = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    public function getEnabledLocales(): ?array
    {
        return $this->enabledLocales;
    }

    public function setEnabledLocales(?array $enabledLocales): void
    {
        $this->enabledLocales = $enabledLocales;
    }

    public function getLocaleFallbacks(): ?array
    {
        return $this->localeFallbacks;
    }

    public function setLocaleFallbacks(?array $localeFallbacks): void
    {
        $this->localeFallbacks = $localeFallbacks;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): void
    {
        $this->public = $public;
    }
}
