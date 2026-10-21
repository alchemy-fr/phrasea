import type {AQLQuery, AttributeDefinition} from '@/types/api';
import {
    BuiltInAttribute,
    emptySearchState,
    quoteAQL,
    searchStateToParams,
} from '@/features/search/searchState';
import {aqlKey, workspaceIdOf} from '@/features/attributes/definitionsStore';
import {routes} from '@/lib/routes';

function entityListIdOf(definition: AttributeDefinition): string | undefined {
    const list = definition.entityList;
    if (!list) {
        return undefined;
    }

    return typeof list === 'string' ? list.split('/').pop() : list.id;
}

/** The attribute definitions of the workspace whose values come from the list */
export function entityListDefinitions(
    definitions: AttributeDefinition[],
    workspaceId: string,
    listId: string
): AttributeDefinition[] {
    return definitions.filter(
        d => workspaceIdOf(d) === workspaceId && entityListIdOf(d) === listId
    );
}

export function entityListSlugs(definitions: AttributeDefinition[]): string[] {
    return [...new Set(definitions.map(aqlKey))];
}

/** Assets holding the entity in any of the given attributes (AQL slugs) */
export function entityAssetsCondition(
    slugs: string[],
    entityId: string
): AQLQuery | undefined {
    if (slugs.length === 0) {
        return undefined;
    }
    const terms = slugs.map(slug => `${slug} = ${quoteAQL(entityId)}`);

    return {
        id: `entity-${entityId}`,
        query: terms.length === 1 ? terms[0] : `(${terms.join(' OR ')})`,
    };
}

export function workspaceCondition(workspaceId: string): AQLQuery {
    return {
        id: BuiltInAttribute.Workspace,
        query: `${BuiltInAttribute.Workspace} = ${quoteAQL(workspaceId)}`,
    };
}

/** The asset search of the entity, within its workspace */
export function entityAssetsSearchUrl(
    slugs: string[],
    entityId: string,
    workspaceId: string
): string | undefined {
    const condition = entityAssetsCondition(slugs, entityId);
    if (!condition) {
        return undefined;
    }

    return `${routes.assets()}?${searchStateToParams({
        ...emptySearchState,
        conditions: [workspaceCondition(workspaceId), condition],
    })}`;
}
