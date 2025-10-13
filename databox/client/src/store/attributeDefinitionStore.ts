import {create} from 'zustand';
import {
    Asset,
    AssetTypeFilter,
    AttributeDefinition,
    AttributeEntity,
    Collection,
    RenditionDefinition,
    Tag,
    User,
    Workspace,
} from '../types';
import {
    getAttributeDefinitions,
    getWorkspaceAttributeDefinitions,
} from '../api/attributes.ts';
import {TFunction} from '@alchemy/i18n';
import {BuiltInField} from '../components/Media/Search/search.ts';
import WorkspaceSelect from '../components/Form/WorkspaceSelect.tsx';
import PrivacyWidget from '../components/Form/PrivacyWidget.tsx';
import TagSelect from '../components/Form/TagSelect.tsx';
import AttributeEntitySelect from '../components/Form/AttributeEntitySelect.tsx';
import UserSelect from '../components/Form/UserSelect.tsx';
import RenditionDefinitionSelect from '../components/Form/RenditionDefinitionSelect.tsx';
import {AttributeType} from '../api/types.ts';
import NullableBooleanWidget from '../components/Form/NullableBooleanWidget.tsx';
import React from 'react';

export type AttributeDefinitionsIndex = Record<string, AttributeDefinition>;

type State = {
    definitions: AttributeDefinition[];
    loaded: boolean;
    loading: boolean;
    locks: Record<string, boolean>;
    load: (t: TFunction, force?: boolean) => Promise<void>;
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

    load: async (t, force) => {
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
            const data = getBuiltInFilters(t).concat(
                (await getAttributeDefinitions()).map(normalizeDefinition)
            );

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
            ).map(normalizeDefinition);

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

export function getBuiltInFilters(t: TFunction): AttributeDefinition[] {
    return (
        [
            {
                slug: BuiltInField.Score,
                fieldType: AttributeType.Number,
                sortable: true,
                searchable: false,
                name: t('built_in_attr.score', 'Score'),
            },
            {
                slug: BuiltInField.Id,
                fieldType: AttributeType.Id,
                sortable: true,
                searchable: true,
                name: t('built_in_attr.id', 'ID'),
                getValueFromAsset: asset => asset.id,
            },
            {
                slug: BuiltInField.Collection,
                entityIri: 'collections',
                resolveLabel: (entity: Collection) =>
                    entity.titleTranslated ?? entity.title ?? '',
                searchable: true,
                fieldType: AttributeType.CollectionPath,
                name: t('built_in_attr.collections', 'Collections'),
                getValueFromAsset: asset =>
                    asset.collections?.filter(c => !c.storyAsset) ?? [],
                multiple: true,
            },
            {
                slug: BuiltInField.Workspace,
                fieldType: AttributeType.Workspace,
                resolveLabel: (entity: Workspace) =>
                    entity.nameTranslated ?? entity.name ?? '',
                entityIri: 'workspaces',
                searchable: true,
                name: t('built_in_attr.workspace', 'Workspace'),
                widget: {
                    component: WorkspaceSelect,
                },
                getValueFromAsset: asset => asset.workspace,
            },
            {
                slug: BuiltInField.Owner,
                fieldType: AttributeType.User,
                resolveLabel: (entity: User) =>
                    entity.username ?? entity.id ?? '',
                entityIri: 'users',
                searchable: true,
                name: t('built_in_attr.owner', 'Owner'),
                widget: {
                    component: UserSelect,
                },
                getValueFromAsset: asset => asset.owner,
            },
            {
                slug: BuiltInField.Privacy,
                fieldType: AttributeType.Privacy,
                searchable: true,
                sortable: true,
                name: t('built_in_attr.privacy', 'Privacy'),
                widget: {
                    component: PrivacyWidget,
                },
                getValueFromAsset: asset => asset.privacy,
            },
            {
                slug: BuiltInField.IsStory,
                fieldType: AttributeType.Boolean,
                searchable: true,
                sortable: true,
                name: t('built_in_attr.isStory', 'Is Story'),
                widget: {
                    component: NullableBooleanWidget,
                },
                getValueFromAsset: asset => !!asset.storyCollection,
            },
            {
                slug: BuiltInField.Story,
                entityIri: 'assets',
                resolveLabel: (entity: Asset) =>
                    entity.resolvedTitle ?? entity.title ?? '',
                searchable: true,
                fieldType: AttributeType.Story,
                name: t('built_in_attr.stories', 'Stories'),
                multiple: true,
                getValueFromAsset: (asset: Asset) =>
                    asset.collections?.filter(c => !!c.storyAsset) ?? [],
            },
            {
                slug: BuiltInField.Tag,
                fieldType: AttributeType.Tag,
                entityIri: 'tags',
                resolveLabel: (entity: Tag) =>
                    entity.nameTranslated ?? entity.name ?? '',
                searchable: true,
                sortable: true,
                multiple: true,
                name: t('built_in_attr.tag', 'Tag'),
                widget: {
                    component: TagSelect,
                    props: {
                        useIRI: false,
                    },
                },
                getValueFromAsset: asset => asset.tags,
            },
            {
                slug: BuiltInField.Rendition,
                fieldType: AttributeType.Rendition,
                entityIri: 'rendition-definitions',
                resolveLabel: (entity: RenditionDefinition) =>
                    entity.nameTranslated ?? entity.name ?? '',
                searchable: true,
                sortable: true,
                multiple: true,
                name: t('built_in_attr.rendition', 'Rendition'),
                widget: {
                    component: RenditionDefinitionSelect,
                    props: {
                        useIRI: false,
                    },
                },
            },
            {
                slug: BuiltInField.EditedAt,
                fieldType: AttributeType.DateTime,
                searchable: true,
                sortable: true,
                name: t('built_in_attr.editedAt', 'Edited At'),
                getValueFromAsset: asset => asset.editedAt,
            },
            {
                slug: BuiltInField.CreatedAt,
                fieldType: AttributeType.DateTime,
                searchable: true,
                sortable: true,
                name: t('built_in_attr.createdAt', 'Created At'),
                getValueFromAsset: asset => asset.createdAt,
            },
            {
                slug: BuiltInField.FileType,
                fieldType: AttributeType.Keyword,
                searchable: true,
                name: t('built_in_attr.fileType', 'File Type'),
                getValueFromAsset: asset => asset.source?.type,
            },
            {
                slug: BuiltInField.FileMimeType,
                fieldType: AttributeType.Keyword,
                searchable: true,
                name: t('built_in_attr.fileMimeType', 'File MIME Type'),
                getValueFromAsset: asset => asset.source?.type,
            },
            {
                slug: BuiltInField.FileSize,
                fieldType: AttributeType.Number,
                searchable: true,
                name: t('built_in_attr.fileSize', 'File Size'),
                getValueFromAsset: asset => asset.source?.size,
            },
            {
                slug: BuiltInField.FileName,
                fieldType: AttributeType.Text,
                searchable: true,
                name: t('built_in_attr.filename', 'File Name'),
            },
        ] as Partial<AttributeDefinition>[]
    ).map(
        d =>
            ({
                ...d,
                id: d.slug,
                searchSlug: d.slug,
                enabled: true,
                builtIn: true,
            }) as AttributeDefinition
    );
}

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
