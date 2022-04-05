<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use Symfony\Component\Serializer\Annotation\Groups;

class AlternateUrlOutput extends AbstractUuidOutput
{
    /**
     * @Groups({"file:index", "file:read", "asset:index", "asset:read"})
     */
    private string $type;

    /**
     * @Groups({"file:index", "file:read", "asset:index", "asset:read"})
     */
    private string $url;

    /**
     * @Groups({"file:index", "file:read", "asset:index", "asset:read"})
     */
    private ?string $label = null;

    public function __construct(string $type, string $url, ?string $label = null)
    {
        $this->type = $type;
        $this->url = $url;
        $this->label = $label;
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
