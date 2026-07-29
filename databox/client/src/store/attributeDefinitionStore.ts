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
import {BuiltInAttributeEnum} from '../components/Media/Search/search.ts';
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

export function getBuiltInAttributeValueResolver(
    field: BuiltInAttributeEnum
): GetValueFromAsset | undefined {
    const index: Partial<Record<BuiltInAttributeEnum, GetValueFromAsset>> = {
        [BuiltInAttributeEnum.Id]: asset => asset.id,
        [BuiltInAttributeEnum.Collection]: asset =>
            asset.collections?.filter(c => !c.storyAsset) ?? [],
        [BuiltInAttributeEnum.Workspace]: asset => asset.workspace,
        [BuiltInAttributeEnum.Owner]: asset => asset.owner,
        [BuiltInAttributeEnum.Privacy]: asset => asset.privacy,
        [BuiltInAttributeEnum.AssetStatus]: asset => asset.status,
        [BuiltInAttributeEnum.IsStory]: asset => !!asset.storyCollection,
        [BuiltInAttributeEnum.Story]: asset =>
            asset.collections?.filter(c => !!c.storyAsset) ?? [],
        [BuiltInAttributeEnum.Tag]: asset => asset.tags,
        [BuiltInAttributeEnum.EditedAt]: asset => asset.editedAt,
        [BuiltInAttributeEnum.CreatedAt]: asset => asset.createdAt,
        [BuiltInAttributeEnum.FileType]: asset => asset.source?.type,
        [BuiltInAttributeEnum.FileExtension]: asset => asset.source?.extension,
        [BuiltInAttributeEnum.FileSize]: asset => asset.source?.size,
        [BuiltInAttributeEnum.FileName]: asset => asset.source?.fileName,
        [BuiltInAttributeEnum.HasSource]: asset => !!asset.source,
        [BuiltInAttributeEnum.Deleted]: asset =>
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
        case BuiltInAttributeEnum.Privacy:
            return {
                ...d,
                type: AttributeType.Privacy,
            };
        case BuiltInAttributeEnum.AssetStatus:
            return {
                ...d,
                type: AttributeType.AssetStatus,
            };
        case BuiltInAttributeEnum.Tag:
            return {
                ...d,
                type: AttributeType.Tag,
            };
        case BuiltInAttributeEnum.Story:
            return {
                ...d,
                type: AttributeType.Story,
            };
        case BuiltInAttributeEnum.Rendition:
            return {
                ...d,
                type: AttributeType.Rendition,
            };
        case BuiltInAttributeEnum.Owner:
            return {
                ...d,
                type: AttributeType.User,
            };
        case BuiltInAttributeEnum.Workspace:
            return {
                ...d,
                type: AttributeType.Workspace,
            };
        default:
            return d;
    }
}
