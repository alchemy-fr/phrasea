import {EntityList, Workspace} from '../../../types';
import {
    deleteEntityList,
    getEntityLists,
    postEntityList,
    putEntityList,
} from '../../../api/entityList.ts';
import {ListItemText, TextField} from '@mui/material';
import {FormFieldErrors, FormRow} from '@alchemy/react-form';
import DefinitionManager, {
    DefinitionItemFormProps,
    DefinitionItemManageProps,
    DefinitionItemProps,
} from './DefinitionManager/DefinitionManager.tsx';
import {useTranslation} from 'react-i18next';
import {DataTabProps} from '../Tabbed/TabbedDialog.tsx';
import AttributeEntityManager from './AttributeEntityManager.tsx';

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

function ManageItem({workspace, data}: DefinitionItemManageProps<EntityList>) {
    return <AttributeEntityManager workspace={workspace} data={data} />;
}

function ListItem({data}: DefinitionItemProps<EntityList>) {
    return (
        <>
            <ListItemText primary={data.name} />
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
            hasSubDefinitions={true}
        />
    );
}
