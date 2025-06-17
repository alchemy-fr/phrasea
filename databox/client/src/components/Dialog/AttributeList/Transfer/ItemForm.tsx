import {
    InputLabel,
    List,
    ListItem,
    ListItemText,
    TextField,
} from '@mui/material';
import {useFormSubmit} from '@alchemy/api';
import {AttributeListItem, AttributeListItemType} from '../../../../types.ts';
import {toast} from 'react-toastify';
import {useDirtyFormPrompt} from '../../Tabbed/FormTab.tsx';
import {useTranslation} from 'react-i18next';
import {FormFieldErrors, FormRow, SwitchWidget} from '@alchemy/react-form';
import {putAttributeListItem} from '../../../../api/attributeList.ts';
import RemoteErrors from '../../../Form/RemoteErrors.tsx';
import {LoadingButton} from '@mui/lab';
import {getAttributeType} from '../../../Media/Asset/Attribute/types';
import {
    useIndexById,
    useIndexBySlug,
} from '../../../../store/attributeDefinitionStore.ts';
import {useAttributeListStore} from '../../../../store/attributeListStore.ts';
import Paper from '@mui/material/Paper';
import Box from '@mui/material/Box';

type Props = {
    item: AttributeListItem;
    listId: string;
    onChange: (item: AttributeListItem) => void;
};

export default function ItemForm({item, listId, onChange}: Props) {
    const {t} = useTranslation();
    const updateAttributeListItem = useAttributeListStore(
        state => state.updateAttributeListItem
    );
    const definitionIndexBySlug = useIndexBySlug();
    const definitionIndexById = useIndexById();

    const {
        submitting,
        remoteErrors,
        forbidNavigation,
        register,
        control,
        handleSubmit,
        formState: {errors},
    } = useFormSubmit<AttributeListItem>({
        defaultValues: item,
        onSubmit: async (data: AttributeListItem) => {
            return await putAttributeListItem(listId, data.id, data);
        },
        onSuccess: data => {
            updateAttributeListItem(listId, data);
            onChange(data);
            toast.success(
                t('form.attribute_list_item.success', 'Item saved!') as string
            );
        },
    });

    useDirtyFormPrompt(forbidNavigation);
    const formId = 'attr-list-item-basket';

    let def = item.definition
        ? definitionIndexById[item.definition]
        : undefined;
    if (item.type === AttributeListItemType.BuiltIn) {
        def = definitionIndexBySlug[item.key!];
    }
    const attributeType = def ? getAttributeType(def!.fieldType) : undefined;
    const formats = attributeType ? attributeType.getAvailableFormats() : [];

    return (
        <form id={formId} onSubmit={handleSubmit}>
            <Paper
                sx={{
                    width: 250,
                    height: 450,
                    display: 'flex',
                    justifyItems: 'space-around',
                    flexDirection: 'column',
                    padding: 2,
                }}
            >
                <Box sx={{overflow: 'auto', p: 1, flexGrow: 1}}>
                    {item.type === AttributeListItemType.Divider ? (
                        <FormRow>
                            <TextField
                                label={t(
                                    'form.attribute_list_item.key.label',
                                    'Value'
                                )}
                                disabled={submitting}
                                {...register('key' as any)}
                            />
                            <FormFieldErrors field={'key'} errors={errors} />
                        </FormRow>
                    ) : null}

                    {![
                        AttributeListItemType.Divider,
                        AttributeListItemType.Spacer,
                    ].includes(item.type) ? (
                        <FormRow>
                            <SwitchWidget
                                control={control}
                                name={'displayEmpty'}
                                label={t(
                                    'form.attribute_list_item.display_empty.label',
                                    'Display even if empty'
                                )}
                                disabled={submitting}
                            />
                        </FormRow>
                    ) : null}

                    {formats.length > 0 && (
                        <FormRow>
                            <InputLabel>
                                {t(
                                    'form.attribute_list_item.format.label',
                                    'Default Format'
                                )}
                            </InputLabel>
                            <List
                                sx={{
                                    mx: -1,
                                    input: {
                                        mr: 1,
                                    },
                                }}
                            >
                                <ListItem component={'label'}>
                                    <input
                                        type="radio"
                                        {...register('format')}
                                        value={''}
                                    />
                                    <ListItemText
                                        primary={t(
                                            'form.attribute_list_item.format.none',
                                            'None'
                                        )}
                                    />
                                </ListItem>
                                {formats.map(f => (
                                    <ListItem key={f.name} component={'label'}>
                                        <input
                                            type="radio"
                                            {...register('format')}
                                            value={f.name}
                                        />
                                        <ListItemText
                                            primary={f.title}
                                            secondary={f.example}
                                        />
                                    </ListItem>
                                ))}
                            </List>
                        </FormRow>
                    )}
                </Box>

                <LoadingButton
                    type={'submit'}
                    loading={submitting}
                    disabled={submitting}
                    variant={'contained'}
                >
                    {t('form.attribute_list_item.save', 'Save')}
                </LoadingButton>
                <RemoteErrors errors={remoteErrors} />
            </Paper>
        </form>
    );
}
