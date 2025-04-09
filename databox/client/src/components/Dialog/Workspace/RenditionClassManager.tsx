import {RenditionClass, Workspace} from '../../../types';
import {InputLabel, ListItemText, TextField} from '@mui/material';
import {FormRow} from '@alchemy/react-form';
import DefinitionManager, {
    DefinitionItemFormProps,
    DefinitionItemProps,
} from './DefinitionManager/DefinitionManager.tsx';
import {useTranslation} from 'react-i18next';
import {FormFieldErrors} from '@alchemy/react-form';
import {
    deleteRenditionClass,
    getRenditionClasses,
    postRenditionClass,
    putRenditionClass,
} from '../../../api/rendition';
import {CheckboxWidget} from '@alchemy/react-form';
import RenditionClassPermissions from './RenditionClassPermissions';
import {DataTabProps} from '../Tabbed/TabbedDialog.tsx';

function Item({
    data,
    usedFormSubmit: {
        submitting,
        register,
        control,
        watch,
        formState: {errors},
    },
}: DefinitionItemFormProps<RenditionClass>) {
    const {t} = useTranslation();

    const isPublic = watch('public');

    return (
        <>
            <FormRow>
                <TextField
                    label={t('form.rendition_class.name.label', 'Name')}
                    {...register('name')}
                    disabled={submitting}
                />
                <FormFieldErrors field={'name'} errors={errors} />
            </FormRow>
            <FormRow>
                <CheckboxWidget
                    label={t('form.rendition_class.public.label', 'Public')}
                    control={control}
                    name={'public'}
                    disabled={submitting}
                />
                <FormFieldErrors field={'public'} errors={errors} />
            </FormRow>
            {data.id && !isPublic && (
                <FormRow>
                    <InputLabel>
                        {t('form.permissions.label', 'Permissions')}
                    </InputLabel>
                    <RenditionClassPermissions
                        classId={data.id}
                        workspaceId={(data.workspace as Workspace).id}
                    />
                </FormRow>
            )}
        </>
    );
}

function ListItem({data}: DefinitionItemProps<RenditionClass>) {
    return <ListItemText primary={data.name} />;
}

function createNewItem(): Partial<RenditionClass> {
    return {
        name: '',
        public: true,
    };
}

type Props = DataTabProps<Workspace>;

export default function RenditionClassManager({
    data: workspace,
    minHeight,
    onClose,
}: Props) {
    const {t} = useTranslation();

    const handleSave = async (data: RenditionClass) => {
        if (data.id) {
            const postData = {...data} as Partial<RenditionClass>;
            delete postData.workspace;

            return await putRenditionClass(data.id, postData);
        } else {
            return await postRenditionClass({
                ...data,
                workspace: `/workspaces/${workspace.id}`,
            });
        }
    };

    return (
        <DefinitionManager
            itemComponent={Item}
            listComponent={ListItem}
            load={() => getRenditionClasses(workspace.id).then(r => r.result)}
            workspace={workspace}
            minHeight={minHeight}
            onClose={onClose}
            createNewItem={createNewItem}
            newLabel={t('rendition_class.new.label', 'New class')}
            handleSave={handleSave}
            handleDelete={deleteRenditionClass}
        />
    );
}
