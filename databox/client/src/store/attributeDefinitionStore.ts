import {create} from 'zustand';
import {
    AttributeDefinition,
    AttributeEntity,
    Collection,
    RenditionDefinition,
    Tag,
    User,
    Workspace,
} from '../types';
import {AttributeType, getAttributeDefinitions} from '../api/attributes.ts';
import {TFunction} from '@alchemy/i18n';
import {BuiltInFilter} from '../components/Media/Search/search.ts';
import WorkspaceSelect from '../components/Form/WorkspaceSelect.tsx';
import PrivacyWidget from '../components/Form/PrivacyWidget.tsx';
import TagSelect from '../components/Form/TagSelect.tsx';
import AttributeEntitySelect from '../components/Form/AttributeEntitySelect.tsx';
import UserSelect from '../components/Form/UserSelect.tsx';
import RenditionDefinitionSelect from '../components/Form/RenditionDefinitionSelect.tsx';

export type AttributeDefinitionsIndex = Record<string, AttributeDefinition>;

type State = {
    definitions: AttributeDefinition[];
    definitionsIndex: AttributeDefinitionsIndex;
    loaded: boolean;
    loading: boolean;
    load: (t: TFunction, force?: boolean) => Promise<void>;
};

export const useAttributeDefinitionStore = create<State>((set, getState) => ({
    loaded: false,
    loading: false,
    definitions: [],
    definitionsIndex: {},

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
                (await getAttributeDefinitions()).map(d =>
                    d.fieldType === AttributeType.Entity
                        ? {
                              ...d,
                              entityIri: 'attribute-entities',
                              resolveLabel: entity =>
                                  (entity as AttributeEntity).value,
                              widget: {
                                  component: AttributeEntitySelect,
                                  props: {
                                      type: d.entityList,
                                  },
                              },
                          }
                        : d
                )
            );
            const index: AttributeDefinitionsIndex = {};

            for (const def of data) {
                index[def.searchSlug] = def;
            }

            set({
                definitions: data,
                definitionsIndex: index,
                loading: false,
                loaded: true,
            });
        } finally {
            set({loading: false});
        }
    },
}));

export function getBuiltInFilters(t: TFunction): AttributeDefinition[] {
    return (
        [
            {
                slug: BuiltInFilter.Score,
                fieldType: AttributeType.Number,
                sortable: true,
                searchable: false,
                name: t('built_in_attr.score', 'Score'),
            },
            {
                slug: BuiltInFilter.Collection,
                entityIri: 'collections',
                resolveLabel: (entity: Collection) =>
                    entity.titleTranslated ?? entity.title ?? '',
                searchable: true,
                fieldType: AttributeType.CollectionPath,
                name: t('built_in_attr.collection', 'Collection'),
                getValueFromAsset: asset => asset.collections,
                multiple: true,
            },
            {
                slug: BuiltInFilter.Workspace,
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
                slug: BuiltInFilter.Owner,
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
                slug: BuiltInFilter.Privacy,
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
                slug: BuiltInFilter.Tag,
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
                slug: BuiltInFilter.Rendition,
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
                slug: BuiltInFilter.EditedAt,
                fieldType: AttributeType.DateTime,
                searchable: true,
                sortable: true,
                name: t('built_in_attr.editedAt', 'Edited At'),
                getValueFromAsset: asset => asset.editedAt,
            },
            {
                slug: BuiltInFilter.CreatedAt,
                fieldType: AttributeType.DateTime,
                searchable: true,
                sortable: true,
                name: t('built_in_attr.createdAt', 'Created At'),
                getValueFromAsset: asset => asset.createdAt,
            },
            {
                slug: BuiltInFilter.FileType,
                fieldType: AttributeType.Keyword,
                searchable: true,
                name: t('built_in_attr.fileType', 'File Type'),
                getValueFromAsset: asset => asset.source?.type,
            },
            {
                slug: BuiltInFilter.FileMimeType,
                fieldType: AttributeType.Keyword,
                searchable: true,
                name: t('built_in_attr.fileMimeType', 'File MIME Type'),
                getValueFromAsset: asset => asset.source?.type,
            },
            {
                slug: BuiltInFilter.FileSize,
                fieldType: AttributeType.Number,
                searchable: true,
                name: t('built_in_attr.fileSize', 'File Size'),
                getValueFromAsset: asset => asset.source?.size,
            },
            {
                slug: BuiltInFilter.FileName,
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

export function useIndexBySlug(): AttributeDefinitionsIndex {
    return useIndexByKey('slug');
}
export function useIndexBySearchSlug(): AttributeDefinitionsIndex {
    return useIndexByKey('searchSlug');
}
export function useIndexById(): AttributeDefinitionsIndex {
    return useIndexByKey('id');
}

function useIndexByKey(
    key: keyof AttributeDefinition
): AttributeDefinitionsIndex {
    const definitions = useAttributeDefinitionStore(s => s.definitions);
    const index: AttributeDefinitionsIndex = {};
    for (const def of definitions) {
        index[def[key] as string] = def;
    }

    return index;
}
