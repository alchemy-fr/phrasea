<?php

declare(strict_types=1);

namespace App\Integration\Phrasea\Expose;

use Alchemy\CoreBundle\Util\LocaleUtil;
use App\Attribute\AttributeInterface;
use App\Attribute\Type\HtmlAttributeType;
use App\Entity\Core\AttributeDefinition;

/**
 * HTML of an attribute in the description of an Expose asset.
 * Everything is escaped but the values of the "html" attributes, already purified when stored.
 */
final readonly class ExposeAttributeHtmlRenderer
{
    /**
     * @param string|array<?string>|null $value
     */
    public function render(
        AttributeDefinition $definition,
        string|array|null $value,
        ?string $locale = null,
    ): ?string {
        if (empty($value)) {
            return null;
        }

        $hasLocale = $locale && AttributeInterface::NO_LOCALE !== $locale;

        $attributeName = $definition->getName();
        if ($hasLocale) {
            $nameTranslations = $definition->getTranslations()['name'] ?? [];
            if (!empty($nameTranslations)) {
                $bestLocale = LocaleUtil::getBestLocale(array_keys($nameTranslations), [$locale]);
                if ($bestLocale) {
                    $attributeName = $nameTranslations[$bestLocale];
                }
            }
        }

        // The type name is compared: an instanceof CodeAttributeType would also match the types extending it.
        $isHtml = HtmlAttributeType::NAME === $definition->getType();
        $formatValue = static fn (string $v): string => $isHtml ? $v : self::escape($v);

        if (is_array($value)) {
            $value = implode(', ', array_map($formatValue, array_filter($value, static fn (?string $v): bool => null !== $v)));
        } else {
            $value = $formatValue($value);
        }

        return sprintf(
            '  <div class="attribute-group">
    <div class="attribute-name attribute-name-type-%1$s attribute-name-%2$s">%3$s</div>
    <div class="attribute-value attribute-value-type-%1$s attribute-name-%2$s"%5$s>%4$s</div>
  </div>
',
            self::escape((string) $definition->getType()),
            self::escape((string) $definition->getSlug()),
            self::escape((string) $attributeName),
            $value,
            $hasLocale ? ' lang="'.self::escape($locale).'"' : '',
        );
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
