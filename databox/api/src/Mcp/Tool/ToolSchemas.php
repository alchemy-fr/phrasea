<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

/**
 * JSON Schemas of the tool arguments shared by several tools.
 */
final class ToolSchemas
{
    final public const array TRANSLATIONS = [
        'type' => ['object', 'null'],
        'description' => 'Translations by field then locale, e.g. {"name": {"fr": "…", "de": "…"}}. Replaces the current translations.',
        'additionalProperties' => [
            'type' => 'object',
            'additionalProperties' => ['type' => 'string'],
        ],
    ];

    final public const array LABELS = [
        'type' => ['object', 'null'],
        'description' => 'Free key/value labels, e.g. {"group": "web"}',
    ];

    final public const array TARGET = [
        'type' => ['integer', 'null'],
        'enum' => [null, 1, 2, 3],
        'description' => 'Kind of assets it applies to: 1 = assets, 2 = stories, 3 = both',
    ];

    /**
     * An attribute value given on asset creation (AttributeInput).
     */
    final public const array ATTRIBUTES = [
        'type' => ['array', 'null'],
        'description' => 'Attribute values, e.g. [{"definitionId": "…", "value": "Sunset"}, {"definitionId": "…", "value": "Coucher de soleil", "locale": "fr"}]. Get the definitions with list_attribute_definitions.',
        'items' => [
            'type' => 'object',
            'properties' => [
                'definitionId' => ['type' => 'string', 'description' => 'Attribute definition ID'],
                'value' => ['description' => 'The value, in the format of the definition type'],
                'locale' => ['type' => 'string', 'description' => 'Locale of the value, for a translatable definition'],
                'position' => ['type' => 'integer', 'description' => 'Position of the value among the values of a multiple definition'],
            ],
            'required' => ['definitionId', 'value'],
        ],
    ];

    /**
     * An action of POST /assets/{id}/attributes (AttributeActionInput).
     */
    final public const array ATTRIBUTE_ACTIONS = [
        'type' => 'array',
        'minItems' => 1,
        'description' => 'Changes to apply, in this order',
        'items' => [
            'type' => 'object',
            'properties' => [
                'action' => [
                    'type' => 'string',
                    'enum' => ['set', 'add', 'delete'],
                    'description' => '"set" replaces every value of the definition (in the locale) by the given one(s), "add" adds a value to a multiple definition, "delete" removes the values of the definition (only the one given by "id", if any)',
                ],
                'definitionId' => ['type' => 'string', 'description' => 'Attribute definition ID'],
                'value' => ['description' => 'The value, in the format of the definition type; a list of values to "set" a multiple definition'],
                'locale' => ['type' => 'string', 'description' => 'Locale of the value, for a translatable definition'],
                'id' => ['type' => 'string', 'description' => 'ID of the attribute (value) to delete'],
                'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1, 'description' => 'Confidence of the value, between 0 and 1'],
            ],
            'required' => ['action'],
        ],
    ];

    /**
     * An action of POST /attributes/batch-update (AttributeActionInput).
     */
    final public const array BATCH_ATTRIBUTE_ACTIONS = [
        'type' => 'array',
        'minItems' => 1,
        'description' => 'Changes to apply, in this order',
        'items' => [
            'type' => 'object',
            'properties' => [
                'action' => [
                    'type' => 'string',
                    'enum' => ['set', 'add', 'delete', 'replace'],
                    'description' => '"set" replaces every value of the definition (in the locale) by the given one(s), "add" adds a value to a multiple definition, "delete" removes the values of the definition (only the given "value(s)" or the attribute "id", if any), "replace" replaces "value" by "replaceWith" inside the text values (of the definition, or of every editable definition without one)',
                ],
                'definitionId' => ['type' => 'string', 'description' => 'Attribute definition ID'],
                'value' => ['description' => 'The value, in the format of the definition type; a list of values to "set" a multiple definition or to "delete"; the searched text (or pattern) of a "replace"'],
                'locale' => ['type' => 'string', 'description' => 'Locale of the value, for a translatable definition'],
                'id' => ['type' => 'string', 'description' => 'ID of the attribute (value) to set or delete'],
                'assets' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'IDs of the assets of this action, instead of the assetIds of the batch'],
                'replaceWith' => ['type' => 'string', 'description' => '"replace" only: the replacement text'],
                'regex' => ['type' => 'boolean', 'description' => '"replace" only: "value" is a regular expression (PostgreSQL syntax, "\\1" back-references in replaceWith)'],
                'flags' => ['type' => 'string', 'description' => '"replace" with regex only: regex flags, e.g. "gi"'],
                'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1, 'description' => 'Confidence of the value, between 0 and 1'],
            ],
            'required' => ['action'],
        ],
    ];

    final public const array PRIVACY = [
        'type' => ['string', 'null'],
        'enum' => [null, 'secret', 'private_in_workspace', 'public_in_workspace', 'private', 'public_for_users', 'public'],
        'description' => 'Visibility: "secret" (owner and granted users only), "private_in_workspace" (listed to the workspace users, content restricted), "public_in_workspace" (open to the workspace users), "private" (listed to every user, content restricted), "public_for_users" (open to every signed-in user), "public" (open to everyone)',
    ];
}
