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
    final public const string NO_LOCALE = '_';
}
