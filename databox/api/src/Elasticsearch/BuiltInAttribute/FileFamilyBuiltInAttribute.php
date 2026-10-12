<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInAttribute;

use App\Attribute\Type\KeywordAttributeType;
use App\Entity\Core\Asset;
use App\Entity\Core\FileFamilyEnum;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * `@family`: kind of the source file (image, audio, video, document, other),
 * derived from its MIME type. See {@see FileFamilyEnum}.
 */
#[AsTaggedItem(index: '@family')]
final class FileFamilyBuiltInAttribute extends AbstractLabelledBuiltInAttribute
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    protected function getAggregationTranslationKey(): string
    {
        return 'file_family';
    }

    public static function getName(): string
    {
        return 'fileFamily';
    }

    public static function getKey(): string
    {
        return '@family';
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        return $asset->getSourceFileFamily();
    }

    #[\Override]
    public function getType(): string
    {
        return KeywordAttributeType::getName();
    }

    #[\Override]
    public function isFacet(): bool
    {
        return true;
    }

    /**
     * @param string|null $value
     */
    #[\Override]
    public function resolveLabel($value): string
    {
        if (null === $value || '' === $value) {
            return '';
        }

        return $this->translator->trans(sprintf('file_family.%s', $value));
    }

    #[\Override]
    protected function getAggregationSize(): int
    {
        return count(FileFamilyEnum::cases());
    }
}
