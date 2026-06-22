import SwitchAttributeLocaleTask from './SwitchAttributeLocaleTask.tsx';
import {useTranslation} from 'react-i18next';
import {TaskComponentProps} from './taskTypes.ts';
import IndexAssetsTask from './IndexAssetsTask.tsx';

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
                definition: null,
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
    ];
}
