<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use Alchemy\WebhookBundle\Normalizer\WebhookSerializationInterface;
use ApiPlatform\Metadata\ApiProperty;
use App\Api\Model\Output\Traits\CapabilitiesDTOTrait;
use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Api\Model\Output\Traits\UpdatedAtDTOTrait;
use App\Entity\Basket\Basket;
use Symfony\Component\Serializer\Annotation\Groups;

class BasketOutput extends AbstractUuidOutput
{
    use CreatedAtDTOTrait;
    use UpdatedAtDTOTrait;
    use CapabilitiesDTOTrait;

    #[ApiProperty(jsonSchemaContext: [
        'type' => 'object',
        'properties' => [
            'canEdit' => 'boolean',
            'canDelete' => 'boolean',
            'canShare' => 'boolean',
            'canEditPermissions' => 'boolean',
        ],
    ])]
    #[Groups([Basket::GROUP_LIST, Basket::GROUP_READ])]
    protected array $capabilities = [];

    /**
     * @var AssetOutput[]
     */
    #[Groups([Basket::GROUP_LIST, Basket::GROUP_READ])]
    protected ?array $assets = null;

    #[Groups([Basket::GROUP_LIST, Basket::GROUP_READ, WebhookSerializationInterface::DEFAULT_GROUP])]
    private ?string $title = null;

    #[Groups([Basket::GROUP_READ, WebhookSerializationInterface::DEFAULT_GROUP])]
    public ?string $description = null;

    #[Groups([Basket::GROUP_READ, WebhookSerializationInterface::DEFAULT_GROUP])]
    public ?int $assetCount = null;

    #[Groups([Basket::GROUP_LIST, Basket::GROUP_READ])]
    private ?string $titleHighlight = null;

    #[Groups([Basket::GROUP_READ])]
    public ?UserOutput $owner = null;

    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    public function setCapabilities(array $capabilities): void
    {
        $this->capabilities = $capabilities;
    }

    public function getAssets(): ?array
    {
        return $this->assets;
    }

    public function setAssets(?array $assets): void
    {
        $this->assets = $assets;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getTitleHighlight(): ?string
    {
        return $this->titleHighlight;
    }

    public function setTitleHighlight(?string $titleHighlight): void
    {
        $this->titleHighlight = $titleHighlight;
    }

    public function getOwner(): ?UserOutput
    {
        return $this->owner;
    }

    public function setOwner(?UserOutput $owner): void
    {
        $this->owner = $owner;
    }
}
