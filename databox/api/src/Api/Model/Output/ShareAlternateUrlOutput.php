<?php

namespace App\Api\Model\Output;

use App\Entity\Core\Share;
use Symfony\Component\Serializer\Attribute\Groups;

final readonly class ShareAlternateUrlOutput
{
    public function __construct(
        private string $name,
        private string $url,
        private ?string $type,
    ) {
    }

    #[Groups([Share::GROUP_READ])]
    public function getName(): string
    {
        return $this->name;
    }

    #[Groups([Share::GROUP_READ])]
    public function getUrl(): string
    {
        return $this->url;
    }

    #[Groups([Share::GROUP_READ])]
    public function getType(): ?string
    {
        return $this->type;
    }
}
