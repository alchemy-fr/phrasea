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
        $this->translations = $translations ? array_map(array_filter(...), $translations) : null;
    }

    public function getFieldTranslations(string $name): array
    {
        if (null === $this->translations) {
            return [];
        }

        return $this->translations[$name] ?? [];
    }

    /**
     * Translations of the field keyed by normalized locale (en-US => en_US), in their stored order.
     * When two keys normalize to the same locale, the one already normalized wins, otherwise the first one.
     *
     * @return array<string, mixed>
     */
    public function getFieldTranslationsByLocale(string $name): array
    {
        $translations = [];
        foreach ($this->getFieldTranslations($name) as $locale => $value) {
            $locale = (string) $locale;
            $normalized = LocaleUtil::normalizeLocale($locale);
            if (!array_key_exists($normalized, $translations) || $locale === $normalized) {
                $translations[$normalized] = $value;
            }
        }

        return $translations;
    }

    public function getTranslatedField(string $name, array $preferredLocales, ?string $fallback): ?string
    {
        $translations = $this->getFieldTranslationsByLocale($name);
        $key = LocaleUtil::getBestLocale(array_keys($translations), $preferredLocales);
        if (null !== $key) {
            return $translations[$key];
        }

        return $fallback;
    }
}
