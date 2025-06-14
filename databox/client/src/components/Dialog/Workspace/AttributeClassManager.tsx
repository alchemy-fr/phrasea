import {useEffect} from 'react';
import {AttributeClass, Workspace} from '../../../types';
import {
    deleteAttributeClass,
    getWorkspaceAttributeClasses,
    postAttributeClass,
    putAttributeClass,
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
}: DefinitionItemFormProps<AttributeClass>) {
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
                    label={t('form.attribute_class.name.label', 'Name')}
                    {...register('name')}
                    disabled={submitting}
                />
                <FormFieldErrors field={'name'} errors={errors} />
            </FormRow>
            <FormRow>
                <CheckboxWidget
                    label={t('form.attribute_class.public.label', 'Public')}
                    control={control}
                    name={'public'}
                    disabled={submitting}
                />
                <FormFieldErrors field={'public'} errors={errors} />
            </FormRow>
            <FormRow>
                <CheckboxWidget
                    label={t('form.attribute_class.editable.label', 'Editable')}
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
                        objectType={PermissionObject.AttributeClass}
                        displayedPermissions={displayedPermissions}
                    />
                </FormRow>
            )}
        </>
    );
}

function ListItem({data}: DefinitionItemProps<AttributeClass>) {
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

function createNewItem(): Partial<AttributeClass> {
    return {
        name: '',
        public: true,
        editable: true,
    };
}

type Props = DataTabProps<Workspace>;

export default function AttributeClassManager({
    data: workspace,
    minHeight,
    onClose,
}: Props) {
    const {t} = useTranslation();

    const handleSave = async (data: AttributeClass) => {
        if (data.id) {
            return await putAttributeClass(data.id, data);
        } else {
            return await postAttributeClass({
                ...data,
                workspace: `/workspaces/${workspace.id}`,
            });
        }
    };

    return (
        <DefinitionManager
            itemComponent={Item}
            listComponent={ListItem}
            load={() => getWorkspaceAttributeClasses(workspace.id)}
            workspace={workspace}
            minHeight={minHeight}
            onClose={onClose}
            createNewItem={createNewItem}
            newLabel={t('attribute_class.new.label', 'New class')}
            handleSave={handleSave}
            handleDelete={deleteAttributeClass}
        />
    );
}
