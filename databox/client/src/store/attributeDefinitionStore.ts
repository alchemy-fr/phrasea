import {create} from 'zustand';
import {
    Asset,
    AssetTypeFilter,
    AttributeDefinition,
    AttributeEntity,
    BuiltInField,
    Workspace,
} from '../types';
import {
    getAttributeDefinitions,
    getBuiltInFields,
    getWorkspaceAttributeDefinitions,
} from '../api/attributes.ts';
import {BuiltInFieldEnum} from '../components/Media/Search/search.ts';
import AttributeEntitySelect from '../components/Form/AttributeEntitySelect.tsx';
import {AttributeType} from '../api/types.ts';
import React from 'react';

export type AttributeDefinitionsIndex = Record<string, AttributeDefinition>;

type State = {
    definitions: AttributeDefinition[];
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
            const [attributeDefinitions, builtInFields] = await Promise.all([
                getAttributeDefinitions(),
                getBuiltInFields(),
            ]);

            const data = builtInFields.result
                .map(normalizeBuiltInFields)
                .concat(attributeDefinitions.result.map(normalizeDefinition));

            set({
                definitions: data,
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

function normalizeBuiltInFields(field: BuiltInField): AttributeDefinition {
    return {
        id: field.key,
        searchSlug: field.key,
        enabled: true,
        builtIn: true,
        slug: field.key,
        fieldType: field.type,
        sortable: field.sortable,
        searchable: field.searchable,
        facetEnabled: field.facetEnabled,
        name: field.name,
        entityList: null,
        editable: false,
        displayName: field.displayName,
    } as AttributeDefinition;
}

type GetValueFromAsset = (asset: Asset) => any;

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
        [BuiltInFieldEnum.IsStory]: asset => !!asset.storyCollection,
        [BuiltInFieldEnum.Story]: asset =>
            asset.collections?.filter(c => !!c.storyAsset) ?? [],
        [BuiltInFieldEnum.Tag]: asset => asset.tags,
        [BuiltInFieldEnum.EditedAt]: asset => asset.editedAt,
        [BuiltInFieldEnum.CreatedAt]: asset => asset.createdAt,
        [BuiltInFieldEnum.FileType]: asset => asset.source?.type,
        [BuiltInFieldEnum.FileMimeType]: asset => asset.source?.type,
        [BuiltInFieldEnum.FileExtension]: asset => asset.source?.extension,
        [BuiltInFieldEnum.FileSize]: asset => asset.source?.size,
        [BuiltInFieldEnum.FileName]: asset => asset.createdAt,
        [BuiltInFieldEnum.HasSource]: asset => !!asset.source,
        [BuiltInFieldEnum.Deleted]: asset =>
            asset.deleted || asset.referenceCollection?.deleted,
    };

    return index[field];
}
//         {
//             slug: BuiltInFieldEnum.Collection,
//             entityIri: 'collections',
//             resolveLabel: (entity: Collection) =>
//                 entity.displayName ?? entity.name ?? '',
//             searchable: true,
//             fieldType: AttributeType.CollectionPath, TODO
//             name: t('built_in_attr.collections', 'Collections'),
//             getValueFromAsset: asset =>
//                 asset.collections?.filter(c => !c.storyAsset) ?? [],
//             multiple: true,
//         },
//         {
//             slug: BuiltInFieldEnum.Workspace,
//             fieldType: AttributeType.Workspace,
//             resolveLabel: (entity: Workspace) =>
//                 entity.displayName ?? entity.name ?? '',
//             entityIri: 'workspaces',
//             searchable: true,
//             name: t('built_in_attr.workspace', 'Workspace'),
//             widget: {
//                 component: WorkspaceSelect, TODO
//             },
//             getValueFromAsset: asset => asset.workspace,
//         },
//         {
//             slug: BuiltInFieldEnum.Owner,
//             fieldType: AttributeType.User,
//             resolveLabel: (entity: User) =>
//                 entity.username ?? entity.id ?? '',
//             entityIri: 'users',
//             searchable: true,
//             name: t('built_in_attr.owner', 'Owner'),
//             widget: {
//                 component: UserSelect, TODO
//             },
//             getValueFromAsset: asset => asset.owner,
//         },
//         {
//             slug: BuiltInFieldEnum.Privacy,
//             fieldType: AttributeType.Privacy,
//             searchable: true,
//             sortable: true,
//             name: t('built_in_attr.privacy', 'Privacy'),
//             widget: {
//                 component: PrivacyWidget,
//             },
//             getValueFromAsset: asset => asset.privacy,
//         },
//         {
//             slug: BuiltInFieldEnum.IsStory,
//             fieldType: AttributeType.Boolean,
//             searchable: true,
//             sortable: true,
//             name: t('built_in_attr.isStory', 'Is Story'),
//             widget: {
//                 component: NullableBooleanWidget
//             },
//             getValueFromAsset: asset => !!asset.storyCollection,
//         },
//         {
//             slug: BuiltInFieldEnum.Story,
//             entityIri: 'assets',
//             resolveLabel: (entity: Asset) =>
//                 entity.resolvedName ?? entity.name ?? '',
//             searchable: true,
//             fieldType: AttributeType.Story,
//             name: t('built_in_attr.stories', 'Stories'),
//             multiple: true,
//             getValueFromAsset: (asset: Asset) =>
//                 asset.collections?.filter(c => !!c.storyAsset) ?? [],
//         },
//         {
//             slug: BuiltInFieldEnum.Tag,
//             fieldType: AttributeType.Tag,
//             entityIri: 'tags',
//             resolveLabel: (entity: Tag) =>
//                 entity.displayName ?? entity.name ?? '',
//             searchable: true,
//             sortable: true,
//             multiple: true,
//             name: t('built_in_attr.tag', 'Tag'),
//             widget: {
//                 component: TagSelect,
//                 props: {
//                     useIRI: false,
//                 },
//             },
//             getValueFromAsset: asset => asset.tags,
//         },
//         {
//             slug: BuiltInFieldEnum.Rendition,
//             fieldType: AttributeType.Rendition,
//             entityIri: 'rendition-definitions',
//             resolveLabel: (entity: RenditionDefinition) =>
//                 entity.displayName ?? entity.name ?? '',
//             searchable: true,
//             sortable: true,
//             multiple: true,
//             name: t('built_in_attr.rendition', 'Rendition'),
//             widget: {
//                 component: RenditionDefinitionSelect,
//                 props: {
//                     useIRI: false,
//                 },
//             },
//         },
//         {
//             slug: BuiltInFieldEnum.EditedAt,
//             fieldType: AttributeType.DateTime,
//             searchable: true,
//             sortable: true,
//             name: t('built_in_attr.editedAt', 'Edited At'),
//             getValueFromAsset: asset => asset.editedAt,
//         },
//         {
//             slug: BuiltInFieldEnum.CreatedAt,
//             fieldType: AttributeType.DateTime,
//             searchable: true,
//             sortable: true,
//             name: t('built_in_attr.createdAt', 'Created At'),
//             getValueFromAsset: asset => asset.createdAt,
//         },
//         {
//             slug: BuiltInFieldEnum.FileType,
//             fieldType: AttributeType.Keyword,
//             searchable: true,
//             name: t('built_in_attr.fileType', 'File Type'),
//             getValueFromAsset: asset => asset.source?.type,
//         },
//         {
//             slug: BuiltInFieldEnum.FileMimeType,
//             fieldType: AttributeType.Keyword,
//             searchable: true,
//             name: t('built_in_attr.fileMimeType', 'File MIME Type'),
//             getValueFromAsset: asset => asset.source?.type,
//         },
//         {
//             slug: BuiltInFieldEnum.FileExtension,
//             fieldType: AttributeType.Keyword,
//             searchable: true,
//             name: t('built_in_attr.fileExtension', 'File Extension'),
//             getValueFromAsset: asset => asset.source?.extension,
//         },
//         {
//             slug: BuiltInFieldEnum.FileSize,
//             fieldType: AttributeType.Number,
//             searchable: true,
//             name: t('built_in_attr.fileSize', 'File Size'),
//             getValueFromAsset: asset => asset.source?.size,
//         },
//         {
//             slug: BuiltInFieldEnum.FileName,
//             fieldType: AttributeType.Text,
//             searchable: true,
//             name: t('built_in_attr.filename', 'File Name'),
//         },
//         {
//             slug: BuiltInFieldEnum.HasSource,
//             fieldType: AttributeType.Boolean,
//             searchable: true,
//             name: t('built_in_attr.has_source', 'Has Source File'),
//             widget: {
//                 component: NullableBooleanWidget,
//             },
//             getValueFromAsset: asset => !!asset.source,
//         },
//         {
//             slug: BuiltInFieldEnum.Deleted,
//             fieldType: AttributeType.Boolean,
//             searchable: true,
//             name: t('built_in_attr.deleted', 'Deleted'),
//             widget: {
//                 component: NullableBooleanWidget,
//             },
//             getValueFromAsset: asset =>
//                 asset.deleted || asset.referenceCollection?.deleted,
//         },
//     ] as Partial<AttributeDefinition>[]
// ).map(
//     d =>
//         ({
//             ...d,
//             id: d.slug,
//             searchSlug: d.slug,
//             enabled: true,
//             builtIn: true,
//         }) as AttributeDefinition
// );

type Filters = {
    workspaceId?: string;
    target?: AssetTypeFilter;
};

export function useIndexBySlug(filters?: Filters): AttributeDefinitionsIndex {
    return useIndexByKey('slug', filters);
}

export function useIndexBySearchSlug(
    filters?: Filters
): AttributeDefinitionsIndex {
    return useIndexByKey('searchSlug', filters);
}

export function useIndexById(filters?: Filters): AttributeDefinitionsIndex {
    return useIndexByKey('id', filters);
}

function useIndexByKey(
    key: keyof AttributeDefinition,
    filters: Filters = {}
): AttributeDefinitionsIndex {
    const definitions = useAttributeDefinitionStore(s => s.definitions);

    return React.useMemo(() => {
        const index: AttributeDefinitionsIndex = {};
        for (const def of definitions) {
            if (
                filters.workspaceId &&
                (def.workspace as Workspace | undefined)?.id !==
                    filters.workspaceId
            ) {
                continue;
            }
            if (filters.target && (def.target & filters.target) === 0) {
                continue;
            }
            index[def[key] as string] = def;
        }

        return index;
        // eslint-disable-next-line react-hooks/use-memo
    }, [definitions, ...Object.values(filters)]);
}
const normalizeDefinition = (d: AttributeDefinition): AttributeDefinition =>
    d.fieldType === AttributeType.Entity
        ? {
              ...d,
              entityIri: 'attribute-entities',
              resolveLabel: (entity: object) =>
                  (entity as AttributeEntity).value,
              widget: {
                  component: AttributeEntitySelect,
                  props: {
                      type: d.entityList,
                  },
              },
          }
        : d;
