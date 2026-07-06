import {create} from 'zustand';
import {
    Asset,
    AssetTypeFilter,
    AttributeDefinition,
    AttributeDefinitionOrBuiltIn,
    BaseAttributeDefinition,
    BuiltInAttribute,
    Workspace,
} from '../types';
import {
    getAttributeDefinitions,
    getBuiltInAttributes,
    getWorkspaceAttributeDefinitions,
} from '../api/attributes.ts';
import {BuiltInFieldEnum} from '../components/Media/Search/search.ts';
import {AttributeType} from '../api/types.ts';
import React from 'react';

export type AttributeDefinitionsIndex<
    T extends BaseAttributeDefinition = AttributeDefinitionOrBuiltIn,
> = Record<string, T>;

type State = {
    definitions: AttributeDefinition[];
    builtIn: BuiltInAttribute[];
    loaded: boolean;
    loading: boolean;
    locks: Record<string, boolean>;
    load: (force?: boolean) => Promise<void>;
    loadWorkspace: (workspaceId: string) => Promise<void>;
    updateDefinition: (definition: AttributeDefinition) => void;
    addDefinition: (definition: AttributeDefinition) => void;
};

export const useAttributeDefinitionStore = create<State>((set, getState) => ({
    loaded: false,
    loading: false,
    locks: {},
    definitions: [],
    builtIn: [],

    updateDefinition: definition => {
        const state = getState();
        const definitions = state.definitions.map(d =>
            d.id === definition.id ? definition : d
        );
        set({
            definitions,
        });
    },

    addDefinition: definition => {
        const state = getState();
        set({
            definitions: [...state.definitions, definition],
        });
    },

    load: async force => {
        const state = getState();
        if (state.loaded && !force) {
            return;
        }

        if (state.loading) {
            return;
        }

        set({
            loading: true,
        });

        try {
            const [attributeDefinitions, builtInAttributes] = await Promise.all(
                [getAttributeDefinitions(), getBuiltInAttributes()]
            );

            set({
                definitions:
                    attributeDefinitions.result.map(normalizeDefinition),
                builtIn: builtInAttributes.result.map(
                    normalizeBuiltInAttribute
                ),
                loading: false,
                loaded: true,
            });
        } finally {
            set({loading: false});
        }
    },

    loadWorkspace: async workspaceId => {
        const state = getState();
        if (state.locks[workspaceId]) {
            return;
        }

        set(p => {
            const locks = {...p.locks};
            locks[workspaceId] = true;
            return {locks};
        });

        try {
            const data = (
                await getWorkspaceAttributeDefinitions({
                    workspaceId,
                    target: AssetTypeFilter.All,
                })
            ).result.map(normalizeDefinition);

            set(p => {
                const locks = {...p.locks};
                delete locks[workspaceId];

                return {
                    locks,
                    definitions: [
                        ...p.definitions.filter(
                            d => !data.some(r => r.id === d.id)
                        ),
                        ...data,
                    ],
                };
            });
        } catch (_e: any) {
            set(p => {
                const locks = {...p.locks};
                delete locks[workspaceId];
                return {locks};
            });
        }
    },
}));

type GetValueFromAsset = (asset: Asset) => any;

type Filters = {
    workspaceId?: string;
    target?: AssetTypeFilter;
};

export function useIndexBySlug<BI extends boolean>(
    withBuiltInAttributes: BI,
    filters?: Filters
) {
    return useIndexByKey<BI>('slug', withBuiltInAttributes, filters);
}

export function useIndexBySearchSlug<BI extends boolean>(
    withBuiltInAttributes: BI,
    filters?: Filters
) {
    return useIndexByKey<BI>('searchSlug', withBuiltInAttributes, filters);
}

export function useIndexById<BI extends boolean>(
    withBuiltInAttributes: BI,
    filters?: Filters
) {
    return useIndexByKey<BI>('id', withBuiltInAttributes, filters);
}

function useIndexByKey<BI extends boolean>(
    key: keyof BaseAttributeDefinition,
    withBuiltInAttributes?: BI,
    filters: Filters = {}
): AttributeDefinitionsIndex<
    BI extends true
        ? AttributeDefinition | BuiltInAttribute
        : AttributeDefinition
> {
    const definitions = useAttributeDefinitionStore(s => s.definitions);
    const builtIn = useAttributeDefinitionStore(s => s.builtIn);

    return React.useMemo(() => {
        const index: AttributeDefinitionsIndex<
            BI extends true
                ? AttributeDefinition | BuiltInAttribute
                : AttributeDefinition
        > = {};

        if (withBuiltInAttributes) {
            for (const bf of builtIn) {
                // @ts-expect-error unknown key type
                index[bf[key] as string] = bf;
            }
        }

        for (const def of definitions) {
            if (!def.enabled) {
                continue;
            }
            if (
                !def.enabled ||
                (filters.workspaceId &&
                    (def.workspace as Workspace | undefined)?.id !==
                        filters.workspaceId) ||
                (filters.target && (def.target & filters.target) === 0)
            ) {
                continue;
            }
            // @ts-expect-error unknown key type
            index[def[key] as string] = def;
        }

        return index;
        // eslint-disable-next-line react-hooks/use-memo
    }, [definitions, builtIn, ...Object.values(filters)]);
}

export function getBuiltInFieldValueResolver(
    field: BuiltInFieldEnum
): GetValueFromAsset | undefined {
    const index: Partial<Record<BuiltInFieldEnum, GetValueFromAsset>> = {
        [BuiltInFieldEnum.Id]: asset => asset.id,
        [BuiltInFieldEnum.Collection]: asset =>
            asset.collections?.filter(c => !c.storyAsset) ?? [],
        [BuiltInFieldEnum.Workspace]: asset => asset.workspace,
        [BuiltInFieldEnum.Owner]: asset => asset.owner,
        [BuiltInFieldEnum.Privacy]: asset => asset.privacy,
        [BuiltInFieldEnum.AssetStatus]: asset => asset.status,
        [BuiltInFieldEnum.IsStory]: asset => !!asset.storyCollection,
        [BuiltInFieldEnum.Story]: asset =>
            asset.collections?.filter(c => !!c.storyAsset) ?? [],
        [BuiltInFieldEnum.Tag]: asset => asset.tags,
        [BuiltInFieldEnum.EditedAt]: asset => asset.editedAt,
        [BuiltInFieldEnum.CreatedAt]: asset => asset.createdAt,
        [BuiltInFieldEnum.FileType]: asset => asset.source?.type,
        [BuiltInFieldEnum.FileExtension]: asset => asset.source?.extension,
        [BuiltInFieldEnum.FileSize]: asset => asset.source?.size,
        [BuiltInFieldEnum.FileName]: asset => asset.source?.fileName,
        [BuiltInFieldEnum.HasSource]: asset => !!asset.source,
        [BuiltInFieldEnum.Deleted]: asset =>
            asset.deleted || asset.referenceCollection?.deleted,
    };

    return index[field];
}

function normalizeBuiltInAttribute(d: BuiltInAttribute): BuiltInAttribute {
    return normalizeDefinition({
        ...d,
        slug: d.id,
        builtIn: true,
    });
}

function normalizeDefinition<T extends BaseAttributeDefinition>(
    definition: T
): T {
    const d = normalizeDefinitionFromId({
        ...definition,
        searchSlug: definition.searchSlug ?? definition.slug,
    });

    switch (d.type) {
        case AttributeType.AttributeEntity:
            return {
                ...d,
                widgetOptions: {
                    list: d.entityList,
                },
            };
        default:
            return d;
    }
}

function normalizeDefinitionFromId<T extends BaseAttributeDefinition>(d: T): T {
    switch (d.id) {
        case BuiltInFieldEnum.Privacy:
            return {
                ...d,
                type: AttributeType.Privacy,
            };
        case BuiltInFieldEnum.AssetStatus:
            return {
                ...d,
                type: AttributeType.AssetStatus,
            };
        case BuiltInFieldEnum.Tag:
            return {
                ...d,
                type: AttributeType.Tag,
            };
        case BuiltInFieldEnum.Story:
            return {
                ...d,
                type: AttributeType.Story,
            };
        case BuiltInFieldEnum.Rendition:
            return {
                ...d,
                type: AttributeType.Rendition,
            };
        case BuiltInFieldEnum.Owner:
            return {
                ...d,
                type: AttributeType.User,
            };
        case BuiltInFieldEnum.Workspace:
            return {
                ...d,
                type: AttributeType.Workspace,
            };
        default:
            return d;
    }
}
