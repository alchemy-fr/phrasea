import SwitchAttributeLocaleTask from './SwitchAttributeLocaleTask.tsx';
import {useTranslation} from 'react-i18next';
import {TaskComponentProps} from './taskTypes.ts';
import IndexAssetsTask from './IndexAssetsTask.tsx';
import IngestWorkspaceAssetsTask from './IngestWorkspaceAssetsTask.tsx';
import AttributeDefinitionTask from './AttributeDefinitionTask.tsx';

type Task = {
    name: string;
    displayName: string;
    description?: string;
    component: React.FunctionComponent<TaskComponentProps>;
    defaultValues?: Record<string, any>;
};

export function useTasks(): Task[] {
    const {t} = useTranslation();
    return [
        {
            component: SwitchAttributeLocaleTask,
            name: 'switch_attribute_locales',
            displayName: t(
                'operation_task.switch_attribute_locales.name',
                'Switch attribute locales'
            ),
            description: t(
                'operation_task.switch_attribute_locales.desc',
                `Switch the locales of attributes. This is useful when you want to change the locale of an attribute without having to delete and recreate it.`
            ),
            defaultValues: {
                definitionId: null,
                fromLocale: null,
                toLocale: null,
            },
        },
        {
            component: IndexAssetsTask,
            name: 'index_assets',
            displayName: t('operation_task.index_assets.name', 'Index assets'),
            description: t(
                'operation_task.index_assets.desc',
                `ReIndex Assets and their Attributes`
            ),
            defaultValues: {
                workspaceId: null,
            },
        },
        {
            component: IngestWorkspaceAssetsTask,
            name: 'ingest_workspace_assets',
            displayName: t(
                'operation_task.ingest_workspace_assets.name',
                'Ingest workspace assets'
            ),
            description: t(
                'operation_task.ingest_workspace_assets.desc',
                `Re-run the ingest workflow on every asset of a workspace`
            ),
            defaultValues: {
                workspaceId: null,
            },
        },
        {
            component: AttributeDefinitionTask,
            name: 'store_fallback_attributes',
            displayName: t(
                'operation_task.store_fallback_attributes.name',
                'Store fallback as attribute'
            ),
            description: t(
                'operation_task.store_fallback_attributes.desc',
                `Resolve the fallback of an attribute and persist it as a real attribute value on every asset of the workspace that has no value yet for that attribute.`
            ),
            defaultValues: {
                workspaceId: null,
                definitionId: null,
            },
        },
        {
            component: AttributeDefinitionTask,
            name: 'recompute_initial_values',
            displayName: t(
                'operation_task.recompute_initial_values.name',
                'Recompute initial values'
            ),
            description: t(
                'operation_task.recompute_initial_values.desc',
                `Recompute the initial value of an attribute on every asset of the workspace. This overwrites the attributes currently stored for that definition.`
            ),
            defaultValues: {
                workspaceId: null,
                definitionId: null,
            },
        },
    ];
}
