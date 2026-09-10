<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use App\Attribute\AttributeInterface;

/**
 * Painless function shared by the attribute entity handlers to keep the "suggestions" nested field
 * of the asset documents (see AssetPostTransformListener) in sync with the entity:
 * - rename: the entries of the entity get the new value;
 * - merge: the entries of the merged entities are re-attached to the main one;
 * - delete: an empty value removes the entries.
 *
 * The call expects the "_entityIds" (entities to update), "_id" (entity to keep)
 * and "_suggestion" (new value) script parameters.
 */
final class AttributeEntitySuggestionsScript
{
    public const string CALL = "updateSuggestions(ctx._source, params['_entityIds'], params['_id'], params['_suggestion']);";

    public static function declaration(): string
    {
        return sprintf(<<<'EOF'
void updateSuggestions(HashMap src, List entityIds, String entityId, String value) {
    if (!(src.%1$s instanceof List)) {
        return;
    }
    if (value.isEmpty()) {
        src.%1$s.removeIf(s -> entityIds.contains(s['entityId']));

        return;
    }
    for (s in src.%1$s) {
        if (entityIds.contains(s['entityId'])) {
            s['entityId'] = entityId;
            s['value'] = value;
        }
    }
    // Merged entities may leave the same (definition, value) twice
    Set seen = new HashSet();
    src.%1$s.removeIf(s -> !seen.add(s['definitionId'] + ':' + s['value']));
}


EOF, AttributeInterface::SUGGESTIONS_FIELD);
    }
}
