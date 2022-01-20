<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use Symfony\Component\Serializer\Annotation\Groups;

class AlternateUrlOutput extends AbstractUuidOutput
{
    /**
     * @Groups({"file:index", "file:read", "asset:index", "asset:read"})
     */
    private ?string $type = null;

    /**
     * @Groups({"file:index", "file:read", "asset:index", "asset:read"})
     */
    private ?string $url = null;

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }
}
