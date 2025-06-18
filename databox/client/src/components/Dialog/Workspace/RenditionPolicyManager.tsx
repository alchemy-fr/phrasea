import {RenditionPolicy, Workspace} from '../../../types';
import {InputLabel, ListItemText, TextField} from '@mui/material';
import {FormRow} from '@alchemy/react-form';
import DefinitionManager, {
    DefinitionItemFormProps,
    DefinitionItemProps,
} from './DefinitionManager/DefinitionManager.tsx';
import {useTranslation} from 'react-i18next';
import {FormFieldErrors} from '@alchemy/react-form';
import {
    deleteRenditionPolicy,
    getRenditionPolicies,
    postRenditionPolicy,
    putRenditionPolicy,
} from '../../../api/rendition';
import {CheckboxWidget} from '@alchemy/react-form';
import RenditionPolicyPermissions from './RenditionPolicyPermissions';
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
}: DefinitionItemFormProps<RenditionPolicy>) {
    const {t} = useTranslation();

    const isPublic = watch('public');

    return (
        <>
            <FormRow>
                <TextField
                    label={t('form.rendition_policy.name.label', 'Name')}
                    {...register('name')}
                    disabled={submitting}
                />
                <FormFieldErrors field={'name'} errors={errors} />
            </FormRow>
            <FormRow>
                <CheckboxWidget
                    label={t('form.rendition_policy.public.label', 'Public')}
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
                    <RenditionPolicyPermissions
                        policyId={data.id}
                        workspaceId={(data.workspace as Workspace).id}
                    />
                </FormRow>
            )}
        </>
    );
}

function ListItem({data}: DefinitionItemProps<RenditionPolicy>) {
    return <ListItemText primary={data.name} />;
}

function createNewItem(): Partial<RenditionPolicy> {
    return {
        name: '',
        public: true,
    };
}

type Props = DataTabProps<Workspace>;

export default function RenditionPolicyManager({
    data: workspace,
    minHeight,
    onClose,
}: Props) {
    const {t} = useTranslation();

    const handleSave = async (data: RenditionPolicy) => {
        if (data.id) {
            const postData = {...data} as Partial<RenditionPolicy>;
            delete postData.workspace;

            return await putRenditionPolicy(data.id, postData);
        } else {
            return await postRenditionPolicy({
                ...data,
                workspace: `/workspaces/${workspace.id}`,
            });
        }
    };

    return (
        <DefinitionManager
            itemComponent={Item}
            listComponent={ListItem}
            load={() => getRenditionPolicies(workspace.id).then(r => r.result)}
            workspace={workspace}
            minHeight={minHeight}
            onClose={onClose}
            createNewItem={createNewItem}
            newLabel={t('rendition_policy.new.label', 'New class')}
            handleSave={handleSave}
            handleDelete={deleteRenditionPolicy}
        />
    );
}
