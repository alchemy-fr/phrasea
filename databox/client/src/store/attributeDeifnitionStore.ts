import {create} from 'zustand';
import {AttributeDefinition} from '../types';
import {getAttributeDefinitions} from '../api/attributes.ts';
import {TFunction} from '@alchemy/i18n'
import {BuiltInFilter} from "../components/Media/Search/search.ts";
import WorkspaceSelect from "../components/Form/WorkspaceSelect.tsx";
import PrivacyWidget from "../components/Form/PrivacyWidget.tsx";
import TagSelect from "../components/Form/TagSelect.tsx";

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
            const data = getBuiltInFilters(t).concat(await getAttributeDefinitions());
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

function getBuiltInFilters(t: TFunction): AttributeDefinition[] {
    return [
        {
            slug: BuiltInFilter.Score,
            fieldType: 'text',
            sortable: true,
            searchable: false,
            name: t('built_in_attr.collection', 'Collection'),
        },
        {
            slug: BuiltInFilter.Collection,
            searchable: true,
            fieldType: 'text',
            name: t('built_in_attr.collection', 'Collection'),
        },
        {
            slug: BuiltInFilter.Workspace,
            fieldType: 'text',
            searchable: true,
            name: t('built_in_attr.workspace', 'Workspace'),
            widget: {
                component: WorkspaceSelect,
            },
        },
        {
            slug: BuiltInFilter.Privacy,
            fieldType: 'number',
            searchable: true,
            sortable: true,
            name: t('built_in_attr.privacy', 'Privacy'),
            widget: {
                component: PrivacyWidget,
            },
        },
        {
            slug: BuiltInFilter.Tag,
            fieldType: 'text',
            searchable: true,
            sortable: true,
            name: t('built_in_attr.tag', 'Tag'),
            widget: {
                component: TagSelect,
                props: {
                    useIRI: false,
                },
            },
        },
        {
            slug: BuiltInFilter.EditedAt,
            fieldType: 'date_time',
            searchable: true,
            sortable: true,
            name: t('built_in_attr.editedAt', 'Edited At'),
        },
        {
            slug: BuiltInFilter.CreatedAt,
            fieldType: 'date_time',
            searchable: true,
            sortable: true,
            name: t('built_in_attr.createdAt', 'Created At'),
        },
        {
            slug: BuiltInFilter.FileType,
            fieldType: 'text',
            searchable: true,
            name: t('built_in_attr.fileType', 'File Type'),
        },
        {
            slug: BuiltInFilter.FileMimeType,
            fieldType: 'text',
            searchable: true,
            name: t('built_in_attr.fileMimeType', 'File MIME Type'),
        },
        {
            slug: BuiltInFilter.FileSize,
            fieldType: 'number',
            searchable: true,
            name: t('built_in_attr.fileSize', 'File Size'),
        },
        {
            slug: BuiltInFilter.FileName,
            fieldType: 'text',
            searchable: true,
            name: t('built_in_attr.filename', 'File Name'),
        },
    ].map(d => ({
        ...d,
        id: d.slug,
        searchSlug: d.slug,
        enabled: true,
        builtIn: true,
    } as AttributeDefinition));
}
