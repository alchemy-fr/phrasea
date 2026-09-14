<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use App\Entity\Core\Share;
use Symfony\Component\Serializer\Attribute\Groups;

final readonly class ShareAttachmentOutput
{
    public function __construct(
        private string $id,
        private ?string $name,
        private string $assetId,
        private string $url,
        private ?string $type,
        private ?int $size,
    ) {
    }

    #[Groups([Share::GROUP_PUBLIC_READ, Share::GROUP_READ])]
    public function getId(): string
    {
        return $this->id;
    }

    #[Groups([Share::GROUP_PUBLIC_READ, Share::GROUP_READ])]
    public function getName(): ?string
    {
        return $this->name;
    }

    #[Groups([Share::GROUP_PUBLIC_READ, Share::GROUP_READ])]
    public function getAssetId(): string
    {
        return $this->assetId;
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
}
