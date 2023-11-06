<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait LocaleTrait
{
    #[ORM\Column(type: Types::STRING, length: 10, nullable: false)]
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
