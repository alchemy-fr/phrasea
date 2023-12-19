import {Tag, Workspace} from '../../../types';
import {Box, ListItemText, TextField} from '@mui/material';
import FormRow from '../../Form/FormRow';
import DefinitionManager, {
    DefinitionItemFormProps,
    DefinitionItemProps,
} from './DefinitionManager';
import {useTranslation} from 'react-i18next';
import {Controller} from 'react-hook-form';
import FormFieldErrors from '../../Form/FormFieldErrors';
import {deleteTag, getTags, postTag, putTag} from '../../../api/tag';
import ColorPicker from '../../Form/ColorPicker';

function Item({
    usedFormSubmit: {
        register,
        control,
        submitting,
        formState: {errors},
    },
}: DefinitionItemFormProps<Tag>) {
    const {t} = useTranslation();

    return (
        <>
            <FormRow>
                <TextField
                    label={t('form.tag.name.label', 'Name')}
                    {...register('name')}
                    disabled={submitting}
                />
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

type Props = {
    data: Workspace;
    onClose: () => void;
    minHeight?: number | undefined;
};

function createNewItem(): Partial<Tag> {
    return {
        name: '',
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
            itemComponent={Item}
            listComponent={ListItem}
            load={() =>
                getTags({
                    workspace: workspace['@id'],
                }).then(r => r.result)
            }
            workspaceId={workspace.id}
            minHeight={minHeight}
            onClose={onClose}
            createNewItem={createNewItem}
            newLabel={t('tags.new.label', 'New tag')}
            handleSave={handleSave}
            handleDelete={deleteTag}
        />
    );
}
