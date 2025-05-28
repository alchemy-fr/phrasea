import {EntityList, Workspace} from '../../../types';
import {
    deleteEntityList,
    getEntityLists,
    postEntityList,
    putEntityList,
} from '../../../api/entityList.ts';
import {ListItemSecondaryAction, ListItemText, TextField} from '@mui/material';
import {FormFieldErrors, FormRow} from '@alchemy/react-form';
import DefinitionManager, {
    DefinitionItemFormProps,
    DefinitionItemManageProps,
    DefinitionListItemProps,
} from './DefinitionManager/DefinitionManager.tsx';
import {useTranslation} from 'react-i18next';
import {DataTabProps} from '../Tabbed/TabbedDialog.tsx';
import AttributeEntityManager from './AttributeEntityManager.tsx';
import IconButton from '@mui/material/IconButton';
import EditIcon from '@mui/icons-material/Edit';

function Item({usedFormSubmit}: DefinitionItemFormProps<EntityList>) {
    const {t} = useTranslation();
    const {
        register,
        submitting,
        formState: {errors},
    } = usedFormSubmit;

    return (
        <>
            <FormRow>
                <TextField
                    label={t('form.entity_type.name.label', 'Type')}
                    {...register('name')}
                    disabled={submitting}
                />
                <FormFieldErrors field={'name'} errors={errors} />
            </FormRow>
        </>
    );
}

function ManageItem({
    workspace,
    data,
    setSubManagementState,
}: DefinitionItemManageProps<EntityList>) {
    return (
        <AttributeEntityManager
            workspace={workspace}
            data={data}
            setSubManagementState={setSubManagementState}
        />
    );
}

function ListItem({data, onEdit}: DefinitionListItemProps<EntityList>) {
    return (
        <>
            <ListItemText primary={data.name} />
            <ListItemSecondaryAction>
                <IconButton
                    onClick={e => {
                        e.stopPropagation();
                        e.preventDefault();
                        onEdit();
                    }}
                >
                    <EditIcon />
                </IconButton>
            </ListItemSecondaryAction>
        </>
    );
}

function createNewItem(): Partial<EntityList> {
    return {
        name: '',
    };
}

type Props = DataTabProps<Workspace>;

export default function EntityListManager({
    data: workspace,
    minHeight,
    onClose,
}: Props) {
    const {t} = useTranslation();

    const handleSave = async (data: EntityList) => {
        if (data.id) {
            return await putEntityList(data.id, data);
        } else {
            return await postEntityList(workspace.id, {
                ...data,
            });
        }
    };

    return (
        <DefinitionManager
            itemComponent={Item}
            manageItemComponent={ManageItem}
            listComponent={ListItem}
            load={() =>
                getEntityLists({
                    workspace: workspace.id,
                }).then(r => r.result)
            }
            workspace={workspace}
            minHeight={minHeight}
            onClose={onClose}
            createNewItem={createNewItem}
            newLabel={t('entity_type.new.label', 'New Entity List')}
            handleSave={handleSave}
            handleDelete={deleteEntityList}
        />
    );
}
