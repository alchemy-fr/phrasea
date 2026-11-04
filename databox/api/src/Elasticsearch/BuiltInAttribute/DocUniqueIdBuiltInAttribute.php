<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInAttribute;

use App\Attribute\Type\KeywordAttributeType;
use App\Entity\Core\Asset;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: self::KEY)]
final class DocUniqueIdBuiltInAttribute extends AbstractBuiltInAttribute
{
    public const string KEY = '@docUniqueId';

    protected function getAggregationTranslationKey(): string
    {
        return 'doc_unique_id';
    }

    public static function getName(): string
    {
        return 'docUniqueId';
    }

    public static function getKey(): string
    {
        return self::KEY;
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        return $asset->getSourceDocUniqueId();
    }

    #[\Override]
    public function getType(): string
    {
        return KeywordAttributeType::getName();
    }

    #[\Override]
    public function isFacet(): bool
    {
        return false;
    }
}
