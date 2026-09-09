<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInAttribute;

/**
 * Matches assets belonging to a collection **or to any of its descendants**:
 * the "collectionPaths" field is analyzed with a path_hierarchy tokenizer, so
 * querying "/a/b" also matches an asset indexed with "/a/b/c".
 */
final class CollectionBuiltInAttribute extends AbstractCollectionBuiltInAttribute
{
    public static function getName(): string
    {
        return 'collectionPaths';
    }

    public static function getKey(): string
    {
        return '@collection';
    }
}
