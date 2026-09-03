<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInAttribute;

/**
 * Matches assets **directly** attached to one of the given collections.
 *
 * Unlike {@see CollectionBuiltInAttribute}, this one targets the "raw" keyword
 * sub field, which holds the un-analyzed absolute path of each collection the
 * asset is a member of. Descendant collections are therefore never matched:
 *
 *     exists(collectionAsset of the asset whose collection id is in the requested list)
 *
 * Usable with the =, !=, IN, NOT_IN, EXISTS and MISSING operators, e.g.:
 *
 *     @directCollection IN ("<collectionId1>", "<collectionId2>")
 */
final class DirectCollectionBuiltInAttribute extends AbstractCollectionBuiltInAttribute
{
    public static function getName(): string
    {
        return CollectionBuiltInAttribute::getName().'.raw';
    }

    public static function getKey(): string
    {
        return '@directCollection';
    }

    #[\Override]
    public function isFacet(): bool
    {
        // The collection facet is already provided by @collection
        return false;
    }

    #[\Override]
    public function isSortable(): bool
    {
        return false;
    }
}
