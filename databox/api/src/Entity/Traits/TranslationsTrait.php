<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use Alchemy\CoreBundle\Util\LocaleUtil;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait TranslationsTrait
{
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $translations = null;

    public function getTranslations(): ?array
    {
        return $this->translations;
    }

    public function setTranslations(?array $translations): void
    {
        $this->translations = $translations ? array_map('array_filter', $translations) : null;
    }

    public function getFieldTranslations(string $name): array
    {
        if (null === $this->translations) {
            return [];
        }

        return $this->translations[$name] ?? [];
    }

    public function getTranslatedField(string $name, array $preferredLocales, ?string $fallback): ?string
    {
        $translations = $this->getFieldTranslations($name);
        $key = LocaleUtil::getBestLocale(array_keys($translations), $preferredLocales);
        if (null !== $key) {
            return $translations[$key];
        }

        return $fallback;
    }
}
