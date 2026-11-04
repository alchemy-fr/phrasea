<?php

declare(strict_types=1);

namespace App\Attribute;

interface AttributeInterface
{
    final public const string ATTRIBUTES_FIELD = 'attrs';
    final public const string STORY_ATTRIBUTES_FIELD = 'storyAttrs';
    /**
     * Nested field of the asset index holding the distinct attribute values used by the search suggestions.
     */
    final public const string SUGGESTIONS_FIELD = 'suggestions';
    /**
     * Nested field of the asset index holding one entry per stored attribute value of the asset,
     * to list the values of a definition across the assets the user can read (see AttributeValueSearch).
     */
    final public const string VALUES_FIELD = 'attributeValues';
    final public const string NO_LOCALE = '_';
}
