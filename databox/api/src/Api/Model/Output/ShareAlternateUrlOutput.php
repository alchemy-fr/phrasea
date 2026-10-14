<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use App\Entity\Core\Share;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * A rendition of a shared asset, downloadable through the share.
 *
 * Several renditions of an asset may point to the same file (e.g. a preview
 * picking the source): `id` is the rendition's, never the file's.
 */
final readonly class ShareAlternateUrlOutput
{
    public function __construct(
        private string $name,
        private string $url,
        private ?string $type,
        private ?string $assetId = null,
        private ?string $id = null,
        private ?string $definitionId = null,
        private ?string $displayName = null,
        private ?int $size = null,
    ) {
    }

    #[Groups([Share::GROUP_PUBLIC_READ, Share::GROUP_READ])]
    public function getId(): ?string
    {
        return $this->id;
    }

    #[Groups([Share::GROUP_PUBLIC_READ, Share::GROUP_READ])]
    public function getDefinitionId(): ?string
    {
        return $this->definitionId;
    }

    #[Groups([Share::GROUP_PUBLIC_READ, Share::GROUP_READ])]
    public function getName(): string
    {
        return $this->name;
    }

    #[Groups([Share::GROUP_PUBLIC_READ, Share::GROUP_READ])]
    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    #[Groups([Share::GROUP_PUBLIC_READ, Share::GROUP_READ])]
    public function getUrl(): string
    {
        return $this->url;
    }

    #[Groups([Share::GROUP_PUBLIC_READ, Share::GROUP_READ])]
    public function getType(): ?string
    {
        return $this->type;
    }

    #[Groups([Share::GROUP_PUBLIC_READ, Share::GROUP_READ])]
    public function getSize(): ?int
    {
        return $this->size;
    }

    #[Groups([Share::GROUP_PUBLIC_READ, Share::GROUP_READ])]
    public function getAssetId(): ?string
    {
        return $this->assetId;
    }
}
