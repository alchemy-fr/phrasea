import {useEffect} from 'react';
import {AttributePolicy, Workspace} from '../../../types';
import {
    deleteAttributePolicy,
    getWorkspaceAttributePolicies,
    postAttributePolicy,
    putAttributePolicy,
} from '../../../api/attributes';
import {Chip, InputLabel, ListItemText, TextField} from '@mui/material';
import {FormFieldErrors, FormRow} from '@alchemy/react-form';
import DefinitionManager, {
    DefinitionItemFormProps,
    DefinitionItemProps,
} from './DefinitionManager/DefinitionManager.tsx';
import {useTranslation} from 'react-i18next';
import {CheckboxWidget} from '@alchemy/react-form';
import AclForm from '../../Acl/AclForm';
import {AclPermission} from '../../Acl/acl';
import {PermissionObject} from '../../Permissions/permissions';
import {DataTabProps} from '../Tabbed/TabbedDialog.tsx';

function Item({
    data,
    usedFormSubmit: {
        submitting,
        register,
        control,
        watch,
        setValue,
        formState: {errors},
    },
}: DefinitionItemFormProps<AttributePolicy>) {
    const {t} = useTranslation();

    const isPublic = watch('public');
    const isEditable = watch('editable');
    const displayedPermissions = !isPublic
        ? [AclPermission.VIEW, AclPermission.EDIT, AclPermission.ALL]
        : [AclPermission.EDIT];

    useEffect(() => {
        if (!isPublic && isEditable) {
            setValue('editable', false);
        }
    }, [isPublic, isEditable]);

    return (
        <>
            <FormRow>
                <TextField
                    label={t('form.attribute_policy.name.label', 'Name')}
                    {...register('name')}
                    disabled={submitting}
                />
                <FormFieldErrors field={'name'} errors={errors} />
            </FormRow>
            <FormRow>
                <CheckboxWidget
                    label={t('form.attribute_policy.public.label', 'Public')}
                    control={control}
                    name={'public'}
                    disabled={submitting}
                />
                <FormFieldErrors field={'public'} errors={errors} />
            </FormRow>
            <FormRow>
                <CheckboxWidget
                    label={t(
                        'form.attribute_policy.editable.label',
                        'Editable'
                    )}
                    control={control}
                    name={'editable'}
                    disabled={!isPublic || submitting}
                />
                <FormFieldErrors field={'editable'} errors={errors} />
            </FormRow>
            {data.id && !isEditable && (
                <FormRow>
                    <InputLabel>
                        {t('form.permissions.label', 'Permissions')}
                    </InputLabel>
                    <AclForm
                        objectId={data.id}
                        objectType={PermissionObject.AttributePolicy}
                        displayedPermissions={displayedPermissions}
                    />
                </FormRow>
            )}
        </>
    );
}

function ListItem({data}: DefinitionItemProps<AttributePolicy>) {
    const {t} = useTranslation();

    const publicLabel = data.public
        ? t('chip.public', 'Public')
        : t('chip.private', 'Private');
    const editableLabel = data.public
        ? data.editable
            ? t('chip.editable', 'Editable')
            : t('chip.read_only', 'Read only')
        : undefined;

    return (
        <ListItemText
            primary={data.name}
            secondaryTypographyProps={{
                component: 'div',
            }}
            secondary={
                <>
                    <Chip
                        color={data.public ? 'success' : 'error'}
                        label={publicLabel}
                        size={'small'}
                    />{' '}
                    {editableLabel ? (
                        <Chip
                            color={data.editable ? 'success' : 'error'}
                            label={editableLabel}
                            size={'small'}
                        />
                    ) : null}
                </>
            }
        />
    );
}

function createNewItem(): Partial<AttributePolicy> {
    return {
        name: '',
        public: true,
        editable: true,
    };
}

type Props = DataTabProps<Workspace>;

export default function AttributePolicyManager({
    data: workspace,
    minHeight,
    onClose,
}: Props) {
    const {t} = useTranslation();

    const handleSave = async (data: AttributePolicy) => {
        if (data.id) {
            return await putAttributePolicy(data.id, data);
        } else {
            return await postAttributePolicy({
                ...data,
                workspace: `/workspaces/${workspace.id}`,
            });
        }
    };

    return (
        <DefinitionManager
            itemComponent={Item}
            listComponent={ListItem}
            load={() => getWorkspaceAttributePolicies(workspace.id)}
            workspace={workspace}
            minHeight={minHeight}
            onClose={onClose}
            createNewItem={createNewItem}
            newLabel={t('attribute_policy.new.label', 'New class')}
            handleSave={handleSave}
            handleDelete={deleteAttributePolicy}
        />
    );
}
