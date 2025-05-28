import {AttributeEntity, EntityList} from '../../../types';
import {
    deleteAttributeEntity,
    getAttributeEntities,
    postAttributeEntity,
    putAttributeEntity,
} from '../../../api/attributeEntity';
import {ListItemText, TextField} from '@mui/material';
import {
    FormFieldErrors,
    FormRow,
    KeyTranslationsWidget,
} from '@alchemy/react-form';
import DefinitionManager, {
    DefinitionItemFormProps,
    DefinitionItemManageProps,
    DefinitionItemProps,
} from './DefinitionManager/DefinitionManager.tsx';
import {useTranslation} from 'react-i18next';
import Flag from '../../Ui/Flag.tsx';
import {DataTabProps} from '../Tabbed/TabbedDialog.tsx';

function Item({
    usedFormSubmit,
    workspace,
}: DefinitionItemFormProps<AttributeEntity>) {
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
                    label={t('form.attribute_entity.value.label', 'Value')}
                    {...register('value')}
                    disabled={submitting}
                />
                <FormFieldErrors field={'value'} errors={errors} />
            </FormRow>
            {(workspace.enabledLocales ?? []).length > 0 ? (
                <FormRow>
                    <KeyTranslationsWidget
                        renderLocale={l => {
                            return (
                                <Flag
                                    sx={{
                                        mr: 1,
                                    }}
                                    locale={l}
                                />
                            );
                        }}
                        locales={workspace.enabledLocales ?? []}
                        name={'translations'}
                        errors={errors}
                        register={register}
                    />
                </FormRow>
            ) : (
                ''
            )}
        </>
    );
}

function ListItem({data}: DefinitionItemProps<AttributeEntity>) {
    return (
        <>
            <ListItemText primary={data.value} />
        </>
    );
}

function createNewItem(): Partial<AttributeEntity> {
    return {
        value: '',
        translations: {},
    };
}

type Props = DefinitionItemManageProps<EntityList> &
    Omit<DataTabProps<EntityList>, 'onClose'>;

export default function AttributeEntityManager({
    data: list,
    minHeight,
    workspace,
    setSubManagementState,
}: Props) {
    const {t} = useTranslation();

    const handleSave = async (data: AttributeEntity) => {
        if (data.id) {
            return await putAttributeEntity(data.id, data);
        } else {
            return await postAttributeEntity(list.id, {
                ...data,
            });
        }
    };

    return (
        <DefinitionManager
            managerFormId={'entity-attribute-manager'}
            itemComponent={Item}
            listComponent={ListItem}
            load={() =>
                getAttributeEntities({
                    list: list.id,
                }).then(r => r.result)
            }
            workspace={workspace}
            minHeight={minHeight}
            createNewItem={createNewItem}
            newLabel={t('attribute_entity.new.label', 'New Entity')}
            handleSave={handleSave}
            handleDelete={deleteAttributeEntity}
            setSubManagementState={setSubManagementState}
        />
    );
}
