<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;

trait TranslatableTrait
{
    /**
     * @ORM\Column(type="string", length=2)
     * @ORM\JoinColumn(nullable=false)
     */
    private ?string $locale = null;

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function hasLocale(): bool
    {
        return null !== $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }
}
