import {create} from 'zustand';
import {useMemo} from 'react';
import {
    AssetTypeFilter,
    AttributeDefinition,
    AttributeDefinitionOrBuiltIn,
    AttributeType,
    BuiltInAttribute,
} from '@/types/api';
import {
    getAttributeDefinitions,
    getBuiltInAttributes,
    getWorkspaceAttributeDefinitions,
} from '@/lib/api/metadata';
import {BuiltInAttribute as BuiltInEnum} from '@/features/search/searchState';

export type DefinitionsIndex<
    T extends AttributeDefinitionOrBuiltIn = AttributeDefinitionOrBuiltIn,
> = Record<string, T>;

type State = {
    definitions: AttributeDefinition[];
    builtIn: BuiltInAttribute[];
    loaded: boolean;
    loading: Promise<void> | null;
    workspaceLoads: Record<string, Promise<void>>;
    load: (force?: boolean) => Promise<void>;
    loadWorkspace: (workspaceId: string) => Promise<void>;
    upsertDefinition: (definition: AttributeDefinition) => void;
    removeDefinition: (id: string) => void;
};

function normalizeDefinition(d: AttributeDefinition): AttributeDefinition {
    return {
        ...d,
        searchSlug: d.searchSlug ?? d.slug,
        enabled: d.enabled ?? true,
    };
}

function normalizeBuiltIn(d: BuiltInAttribute): BuiltInAttribute {
    return {
        ...d,
        builtIn: true,
        searchSlug: d.searchSlug ?? d.slug,
        enabled: true,
    };
}

export const useDefinitionsStore = create<State>((set, get) => ({
    definitions: [],
    builtIn: [],
    loaded: false,
    loading: null,
    workspaceLoads: {},

    load: async force => {
        const state = get();
        if (state.loaded && !force) {
            return;
        }
        if (state.loading) {
            return state.loading;
        }
        const promise = (async () => {
            try {
                const [definitions, builtIn] = await Promise.all([
                    getAttributeDefinitions(),
                    getBuiltInAttributes(),
                ]);
                set({
                    definitions: definitions.map(normalizeDefinition),
                    builtIn: builtIn.map(normalizeBuiltIn),
                    loaded: true,
                });
            } catch (e) {
                // Callers fire-and-forget: never leave an unhandled rejection.
                console.warn('Failed to load attribute definitions', e);
            } finally {
                set({loading: null});
            }
        })();
        set({loading: promise});

        return promise;
    },

    loadWorkspace: async workspaceId => {
        const existing = get().workspaceLoads[workspaceId];
        if (existing) {
            return existing;
        }
        const promise = (async () => {
            try {
                const page = await getWorkspaceAttributeDefinitions({
                    workspaceId,
                    target: AssetTypeFilter.All,
                });
                const fresh = page.items.map(normalizeDefinition);
                set(s => ({
                    definitions: [
                        ...s.definitions.filter(
                            d => workspaceIdOf(d) !== workspaceId
                        ),
                        ...fresh,
                    ],
                }));
            } finally {
                set(s => {
                    const {[workspaceId]: _omit, ...rest} = s.workspaceLoads;

                    return {workspaceLoads: rest};
                });
            }
        })();
        set(s => ({
            workspaceLoads: {...s.workspaceLoads, [workspaceId]: promise},
        }));

        return promise;
    },

    upsertDefinition: definition =>
        set(s => ({
            definitions: s.definitions.some(d => d.id === definition.id)
                ? s.definitions.map(d =>
                      d.id === definition.id ? definition : d
                  )
                : [...s.definitions, definition],
        })),

    removeDefinition: id =>
        set(s => ({definitions: s.definitions.filter(d => d.id !== id)})),
}));

export function workspaceIdOf(
    definition: AttributeDefinition
): string | undefined {
    const ws = definition.workspace;

    return typeof ws === 'string' ? ws.split('/').pop() : ws?.id;
}

/**
 * Index of definitions by search slug (`@createdAt`, `title`...) — used by AQL.
 */
export function useDefinitionsBySlug(
    options: {
        workspaceId?: string;
        target?: AssetTypeFilter;
        includeBuiltIn?: boolean;
    } = {}
): DefinitionsIndex {
    const definitions = useDefinitionsStore(s => s.definitions);
    const builtIn = useDefinitionsStore(s => s.builtIn);
    const {
        workspaceId,
        target = AssetTypeFilter.All,
        includeBuiltIn = true,
    } = options;

    return useMemo(() => {
        const index: DefinitionsIndex = {};
        if (includeBuiltIn) {
            builtIn.forEach(b => {
                index[b.searchSlug] = b;
            });
        }
        definitions.forEach(d => {
            if (!d.enabled) {
                return;
            }
            if (workspaceId && workspaceIdOf(d) !== workspaceId) {
                return;
            }
            if (target && d.target && (d.target & target) === 0) {
                return;
            }
            index[d.searchSlug] = d;
        });

        return index;
    }, [definitions, builtIn, workspaceId, target, includeBuiltIn]);
}

/**
 * Index by id (and by built-in slug for built-ins).
 */
export function useDefinitionsById(
    options: {
        workspaceId?: string;
        target?: AssetTypeFilter;
        includeBuiltIn?: boolean;
    } = {}
): DefinitionsIndex {
    const definitions = useDefinitionsStore(s => s.definitions);
    const builtIn = useDefinitionsStore(s => s.builtIn);
    const {
        workspaceId,
        target = AssetTypeFilter.All,
        includeBuiltIn = true,
    } = options;

    return useMemo(() => {
        const index: DefinitionsIndex = {};
        if (includeBuiltIn) {
            builtIn.forEach(b => {
                index[b.searchSlug] = b;
            });
        }
        definitions.forEach(d => {
            if (workspaceId && workspaceIdOf(d) !== workspaceId) {
                return;
            }
            if (target && d.target && (d.target & target) === 0) {
                return;
            }
            index[d.id] = d;
        });

        return index;
    }, [definitions, builtIn, workspaceId, target, includeBuiltIn]);
}

type BuiltInResolver = (asset: import('@/types/api').Asset) => unknown;

export const builtInValueResolvers: Partial<
    Record<BuiltInEnum, BuiltInResolver>
> = {
    [BuiltInEnum.Checksum]: a => a.source?.checksum,
    [BuiltInEnum.Collection]: a => a.collections,
    [BuiltInEnum.CreatedAt]: a => a.createdAt,
    [BuiltInEnum.DocUniqueId]: a => a.source?.docUniqueId,
    [BuiltInEnum.EditedAt]: a => a.editedAt,
    [BuiltInEnum.FileExtension]: a => a.source?.extension,
    [BuiltInEnum.FileName]: a => a.source?.fileName,
    [BuiltInEnum.HasSource]: a => !!a.source,
    [BuiltInEnum.FileSize]: a => a.source?.size,
    [BuiltInEnum.FileType]: a => a.source?.type,
    [BuiltInEnum.Id]: a => a.id,
    [BuiltInEnum.Owner]: a => a.owner,
    [BuiltInEnum.Privacy]: a => a.privacy,
    [BuiltInEnum.Tag]: a => a.tags,
    [BuiltInEnum.IsStory]: a => !!a.storyCollection,
    [BuiltInEnum.Story]: a => a.storyCollection,
    [BuiltInEnum.Workspace]: a => a.workspace,
    [BuiltInEnum.Deleted]: a => !!a.deleted,
    [BuiltInEnum.AssetStatus]: a => a.status,
};

export const builtInTypes: Partial<Record<BuiltInEnum, AttributeType>> = {
    [BuiltInEnum.CreatedAt]: AttributeType.DateTime,
    [BuiltInEnum.EditedAt]: AttributeType.DateTime,
    [BuiltInEnum.FileSize]: AttributeType.FileSize,
    [BuiltInEnum.Privacy]: AttributeType.Privacy,
    [BuiltInEnum.Tag]: AttributeType.Tag,
    [BuiltInEnum.Collection]: AttributeType.CollectionPath,
    [BuiltInEnum.Workspace]: AttributeType.Workspace,
    [BuiltInEnum.Owner]: AttributeType.User,
    [BuiltInEnum.AssetStatus]: AttributeType.AssetStatus,
    [BuiltInEnum.Story]: AttributeType.Story,
};
