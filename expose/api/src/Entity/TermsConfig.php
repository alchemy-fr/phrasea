<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Embeddable()
 */
class TermsConfig
{
    /**
     * @ORM\Column(type="text", nullable=true)
     * @Groups({"profile:read", "publication:read"})
     */
    private ?string $text = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"profile:read", "publication:read"})
     */
    private ?string $url = null;

    /**
     * @ORM\Column(type="boolean")
     * @Groups({"profile:read", "publication:read"})
     */
    private bool $mustAccept = false;

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(?string $text): void
    {
        $this->text = $text;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }

    public function isMustAccept(): bool
    {
        return $this->mustAccept;
    }

    public function setMustAccept(bool $mustAccept): void
    {
        $this->mustAccept = $mustAccept;
    }
}
