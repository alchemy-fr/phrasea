import {AttributeDefinitionOrBuiltIn, AttributeType} from '@/types/api';
import {getSearchSuggestions} from '@/lib/api/assets';
import {getCollections} from '@/lib/api/collections';
import {getAttributeEntities, getTags} from '@/lib/api/metadata';
import {idFromIri} from '@/lib/utils/iri';
import {Candidate, isApiSuggestable} from './filterSuggestions';

const LIMIT = 10;

/**
 * Values of a field fetched from the API while typing: collections, tags,
 * the entities of an entity list, or the indexed values of an attribute.
 */
export function hasRemoteValues(
    definition: AttributeDefinitionOrBuiltIn
): boolean {
    switch (definition.type) {
        case AttributeType.CollectionPath:
        case AttributeType.Tag:
            return true;
        case AttributeType.Entity:
            return !!entityListId(definition);
        default:
            return isApiSuggestable(definition);
    }
}

function entityListId(
    definition: AttributeDefinitionOrBuiltIn
): string | undefined {
    const list = definition.entityList;
    if (!list) {
        return undefined;
    }

    return typeof list === 'string' ? idFromIri(list) : list.id;
}

export async function fetchRemoteValues({
    definition,
    definitionIds,
    prefix,
    signal,
}: {
    definition: AttributeDefinitionOrBuiltIn;
    /** Ids of the definitions sharing the field's slug (one per workspace) */
    definitionIds: string[];
    prefix: string;
    signal?: AbortSignal;
}): Promise<Candidate[]> {
    const query = prefix.trim() || undefined;

    switch (definition.type) {
        case AttributeType.CollectionPath: {
            const page = await getCollections({query, limit: LIMIT});

            return page.items.map(c => ({
                value: c.id,
                label:
                    c.absoluteDisplayName ??
                    c.absoluteName ??
                    c.displayName ??
                    c.name,
            }));
        }
        case AttributeType.Tag: {
            const page = await getTags({query});

            return page.items.slice(0, LIMIT).map(tag => ({
                value: tag.id,
                label: tag.displayName ?? tag.name,
            }));
        }
        case AttributeType.Entity: {
            const page = await getAttributeEntities({
                list: entityListId(definition),
                query,
            });

            return page.items
                .slice(0, LIMIT)
                .map(entity => ({value: entity.id, label: entity.value}));
        }
    }

    if (!definitionIds.length) {
        return [];
    }
    const page = await getSearchSuggestions(
        prefix.trim(),
        {definitions: definitionIds},
        signal
    );

    return page.items.map(item => ({
        value: item.name,
        label: item.name,
        hl: item.hl,
    }));
}
