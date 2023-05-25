<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use Symfony\Component\Serializer\Annotation\Groups;

class AlternateUrlOutput extends AbstractUuidOutput
{
    public function __construct(
        /**
         * @Groups({"file:index", "file:read", "asset:index", "asset:read"})
         */
        private readonly string $type,
        /**
         * @Groups({"file:index", "file:read", "asset:index", "asset:read"})
         */
        private readonly string $url,
        /**
         * @Groups({"file:index", "file:read", "asset:index", "asset:read"})
         */
        private readonly ?string $label = null
    ) {
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }
}
