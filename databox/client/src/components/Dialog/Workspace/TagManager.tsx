import {Tag, Workspace} from '../../../types';
import {Box, ListItemText, TextField} from '@mui/material';
import {FormRow, TranslatedField} from '@alchemy/react-form';
import DefinitionManager, {
    DefinitionItemFormProps,
    DefinitionItemProps,
} from './DefinitionManager/DefinitionManager.tsx';
import {useTranslation} from 'react-i18next';
import {Controller} from 'react-hook-form';
import {FormFieldErrors} from '@alchemy/react-form';
import {deleteTag, getTag, getTags, postTag, putTag} from '../../../api/tag';
import {ColorPicker} from '@alchemy/react-form';
import React from 'react';
import {useCreateSaveTranslations} from '../../../hooks/useCreateSaveTranslations.ts';
import {DataTabProps} from '../Tabbed/TabbedDialog.tsx';
import InfoRow from '../Info/InfoRow.tsx';
import KeyIcon from '@mui/icons-material/Key';
import {getLocaleOptions} from '../../../api/locale.ts';
import {useWorkspace} from '../../../hooks/useWorkspace.ts';
import {search} from '../../../lib/search.ts';

function Item({
    data,
    usedFormSubmit: {
        getValues,
        register,
        control,
        setValue,
        submitting,
        formState: {errors},
    },
    workspace,
}: DefinitionItemFormProps<Tag>) {
    const {t} = useTranslation();
    const enabledLocales = useWorkspace(workspace.id)?.enabledLocales;

    const createSaveTranslations = useCreateSaveTranslations({
        data,
        setValue,
        putFn: putTag,
    });

    return (
        <>
            <FormRow>
                {data.id ? (
                    <FormRow>
                        <InfoRow
                            label={t('common.id', `ID`)}
                            value={data.id}
                            copyValue={data.id}
                            icon={<KeyIcon />}
                        />
                    </FormRow>
                ) : null}
                <TranslatedField<Tag>
                    locales={enabledLocales}
                    noToast={!data?.id}
                    getLocales={getLocaleOptions}
                    field={'name'}
                    getData={getValues}
                    title={t('form.tag.translate.name', 'Translate Name')}
                    onUpdate={createSaveTranslations('name')}
                >
                    <TextField
                        label={t('form.tag.name.label', 'Name')}
                        {...register('name')}
                        disabled={submitting}
                    />
                </TranslatedField>

                <FormFieldErrors field={'name'} errors={errors} />
            </FormRow>
            <FormRow>
                <Controller
                    control={control}
                    render={({field: {onChange, value}}) => {
                        return (
                            <ColorPicker
                                color={value || undefined}
                                onChange={onChange}
                                disabled={submitting}
                                label={t('form.tag.color.label', 'Color')}
                                readOnly={submitting}
                            />
                        );
                    }}
                    name={'color'}
                />
                <FormFieldErrors field={'color'} errors={errors} />
            </FormRow>
        </>
    );
}

function ListItem({data}: DefinitionItemProps<Tag>) {
    return (
        <>
            <Box
                sx={theme => ({
                    width: theme.spacing(2),
                    height: theme.spacing(2),
                    borderRadius: theme.shape.borderRadius,
                    border: `1px solid #000`,
                    backgroundColor: data.color,
                    mr: 2,
                })}
            />
            <ListItemText primary={data.name} />
        </>
    );
}

type Props = DataTabProps<Workspace>;

function createNewItem(): Partial<Tag> {
    return {
        name: '',
        color: null,
    };
}

export default function TagManager({
    data: workspace,
    minHeight,
    onClose,
}: Props) {
    const {t} = useTranslation();

    const handleSave = async (data: Tag) => {
        if (data.id) {
            return await putTag(data.id, data);
        } else {
            return await postTag({
                ...data,
                workspace: `/workspaces/${workspace.id}`,
            });
        }
    };

    return (
        <DefinitionManager
            searchFilter={({items}, value) =>
                search<Tag>(items, ['nameTranslated', 'name'], value)
            }
            itemComponent={Item}
            listComponent={ListItem}
            load={() =>
                getTags({
                    workspace: workspace['@id']!,
                }).then(r => r.result)
            }
            loadItem={id => getTag(id)}
            workspace={workspace}
            minHeight={minHeight}
            onClose={onClose}
            createNewItem={createNewItem}
            newLabel={t('tags.new.label', 'New tag')}
            handleSave={handleSave}
            handleDelete={deleteTag}
        />
    );
}
