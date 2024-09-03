import {AttributeEntity, Workspace} from '../../../types';
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
    DefinitionItemProps,
} from './DefinitionManager';
import {useTranslation} from 'react-i18next';
import Flag from '../../Ui/Flag.tsx';

let lastType = '';

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
                    label={t('form.attribute_entity.type.label', 'Type')}
                    {...register('type')}
                    disabled={submitting}
                />
                <FormFieldErrors field={'type'} errors={errors} />
            </FormRow>
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
        type: lastType,
        translations: {},
    };
}

type Props = {
    data: Workspace;
    onClose: () => void;
    minHeight?: number | undefined;
};

export default function AttributeEntityManager({
    data: workspace,
    minHeight,
    onClose,
}: Props) {
    const {t} = useTranslation();

    const handleSave = async (data: AttributeEntity) => {
        if (data.id) {
            return await putAttributeEntity(data.id, data);
        } else {
            lastType = data.type;

            return await postAttributeEntity(workspace.id, {
                ...data,
            });
        }
    };

    return (
        <DefinitionManager
            itemComponent={Item}
            listComponent={ListItem}
            load={() =>
                getAttributeEntities({
                    workspace: workspace.id,
                }).then(r => r.result)
            }
            workspace={workspace}
            minHeight={minHeight}
            onClose={onClose}
            createNewItem={createNewItem}
            newLabel={t('attribute_entity.new.label', 'New Entity')}
            handleSave={handleSave}
            handleDelete={deleteAttributeEntity}
        />
    );
}
