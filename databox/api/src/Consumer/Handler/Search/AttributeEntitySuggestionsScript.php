<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use App\Attribute\AttributeInterface;

/**
 * Painless function shared by the attribute entity handlers to keep the "suggestions" nested field
 * of the asset documents (see AssetPostTransformListener) in sync with the entity:
 * - rename or translation change: the entries of the entity get the new label of their locale;
 * - merge: the entries of the merged entities are re-attached to the main one;
 * - delete: no label at all removes the entries.
 *
 * The call expects the "_entityIds" (entities to update), "_id" (entity to keep)
 * and "_labels" (locale => label, see labels()) script parameters.
 */
final class AttributeEntitySuggestionsScript
{
    public const string CALL = "updateSuggestions(ctx._source, params['_entityIds'], params['_id'], params['_labels']);";

    /**
     * Encodes the labels as a JSON object even when empty (a JSON array is not a Map for Painless).
     *
     * @param array<string, string> $labels
     */
    public static function labels(array $labels): \stdClass
    {
        return (object) $labels;
    }

    /**
     * Statement removing the entries of the definitions given by the "_definitionIds" parameter
     * (their entity list has been cleared).
     */
    public static function removeDefinitionsCall(): string
    {
        return sprintf(
            "if (ctx._source.%1\$s instanceof List) { List definitionIds = params['_definitionIds']; ctx._source.%1\$s.removeIf(s -> definitionIds.contains(s['definitionId'])); }",
            AttributeInterface::SUGGESTIONS_FIELD,
        );
    }

    public static function declaration(): string
    {
        return sprintf(<<<'EOF'
void updateSuggestions(Map src, List entityIds, String entityId, Map labels) {
    def list = src['%1$s'];
    if (!(list instanceof List)) {
        return;
    }
    if (labels.isEmpty()) {
        list.removeIf(s -> entityIds.contains(s['entityId']));

        return;
    }
    // Locales that are not suggested anymore
    list.removeIf(s -> entityIds.contains(s['entityId']) && !labels.containsKey(s['locale']));
    for (s in list) {
        if (entityIds.contains(s['entityId'])) {
            s['entityId'] = entityId;
            s['value'] = labels[s['locale']];
        }
    }
    // Merged entities may leave the same (definition, locale, value) twice
    Set seen = new HashSet();
    list.removeIf(s -> !seen.add(s['definitionId'] + ':' + s['locale'] + ':' + s['value']));
}


EOF, AttributeInterface::SUGGESTIONS_FIELD);
    }
}
