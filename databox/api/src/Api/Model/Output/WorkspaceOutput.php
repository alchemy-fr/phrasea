<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use Alchemy\WebhookBundle\Normalizer\WebhookSerializationInterface;
use App\Api\Model\Output\Traits\CapabilitiesDTOTrait;
use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Entity\Core\Asset;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Collection;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\Workspace;
use Symfony\Component\Serializer\Annotation\Groups;

class WorkspaceOutput extends AbstractUuidOutput
{
    use CapabilitiesDTOTrait;
    use CreatedAtDTOTrait;

    #[Groups([
        '_',
        Workspace::GROUP_LIST,
        Workspace::GROUP_READ,
        Collection::GROUP_LIST,
        Collection::GROUP_READ,
    ])]
    protected array $capabilities = [];

    #[Groups([
        Workspace::GROUP_LIST,
        Workspace::GROUP_READ,
        Collection::GROUP_LIST,
        Collection::GROUP_READ,
        Asset::GROUP_LIST,
        Asset::GROUP_READ,
        WebhookSerializationInterface::DEFAULT_GROUP,
        RenditionDefinition::GROUP_LIST,
        ResolveEntitiesOutput::GROUP_READ,
        AttributeDefinition::GROUP_LIST,
        AttributeDefinition::GROUP_READ,
    ])]
    private string $name;

    #[Groups([
        Workspace::GROUP_LIST,
        Workspace::GROUP_READ,
        Collection::GROUP_LIST,
        Collection::GROUP_READ,
        Asset::GROUP_LIST,
        Asset::GROUP_READ,
        WebhookSerializationInterface::DEFAULT_GROUP,
        RenditionDefinition::GROUP_LIST,
        ResolveEntitiesOutput::GROUP_READ,
        AttributeDefinition::GROUP_LIST,
        AttributeDefinition::GROUP_READ,
    ])]
    public ?string $nameTranslated;

    #[Groups([Workspace::GROUP_LIST, Workspace::GROUP_READ])]
    private string $slug;

    #[Groups([Workspace::GROUP_LIST, Workspace::GROUP_READ])]
    private bool $public;

    #[Groups([
        Workspace::GROUP_LIST,
        Workspace::GROUP_READ,
    ])]
    private ?array $enabledLocales = null;

    #[Groups([Workspace::GROUP_READ])]
    private ?array $localeFallbacks = null;

    #[Groups([Workspace::GROUP_READ])]
    public ?string $fileAnalyzers;

    #[Groups([Workspace::GROUP_READ])]
    public ?array $translations = null;

    #[Groups([Workspace::GROUP_LIST, Workspace::GROUP_READ])]
    public ?string $ownerId = null;

    #[Groups([Workspace::GROUP_READ])]
    public ?UserOutput $owner = null;

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
