<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use App\Entity\Core\Asset;
use App\Entity\Core\Tag;
use Symfony\Component\Serializer\Annotation\Groups;

class TagOutput extends AbstractUuidOutput
{
    #[Groups([
        Asset::GROUP_LIST,
        Asset::GROUP_READ,
        Tag::GROUP_LIST,
        Tag::GROUP_READ,
        ResolveEntitiesOutput::GROUP_READ,
    ])]
    private string $name;

    #[Groups([
        Asset::GROUP_LIST,
        Asset::GROUP_READ,
        Tag::GROUP_LIST,
        Tag::GROUP_READ,
        ResolveEntitiesOutput::GROUP_READ,
    ])]
    public string $nameTranslated;

    #[Groups([Tag::GROUP_READ])]
    public ?array $translations = null;

    #[Groups([
        Asset::GROUP_LIST,
        Asset::GROUP_READ,
        Tag::GROUP_LIST,
        Tag::GROUP_READ,
        ResolveEntitiesOutput::GROUP_READ,
    ])]
    private ?string $color = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): void
    {
        $this->color = $color;
    }
}
