import {
    Button,
    InputLabel,
    List,
    ListItem,
    ListItemText,
    TextField,
} from '@mui/material';
import {useFormSubmit} from '@alchemy/api';
import {ProfileItem, ProfileItemType} from '../../../../types.ts';
import {toast} from 'react-toastify';
import {useDirtyFormPrompt} from '@alchemy/phrasea-framework';
import {useTranslation} from 'react-i18next';
import {FormFieldErrors, FormRow, SwitchWidget} from '@alchemy/react-form';
import {putProfileItem} from '../../../../api/profile.ts';

import {RemoteErrors} from '@alchemy/react-form';
import {getAttributeType} from '../../../Media/Asset/Attribute/types';
import {
    useIndexById,
    useIndexBySlug,
} from '../../../../store/attributeDefinitionStore.ts';
import {useProfileStore} from '../../../../store/profileStore.ts';
import Paper from '@mui/material/Paper';
import Box from '@mui/material/Box';

type Props = {
    item: ProfileItem;
    listId: string;
    onChange: (item: ProfileItem) => void;
};

export default function ItemForm({item, listId, onChange}: Props) {
    const {t, i18n} = useTranslation();
    const updateProfileItem = useProfileStore(state => state.updateProfileItem);
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
    } = useFormSubmit<ProfileItem>({
        defaultValues: item,
        onSubmit: async (data: ProfileItem) => {
            return await putProfileItem(listId, data.id, data);
        },
        onSuccess: data => {
            updateProfileItem(listId, data);
            onChange(data);
            toast.success(
                t('form.profile_item.success', 'Item saved!') as string
            );
        },
    });

    useDirtyFormPrompt(forbidNavigation);
    const formId = 'attr-list-item-basket';

    let def = item.definition
        ? definitionIndexById[item.definition]
        : undefined;
    if (item.type === ProfileItemType.BuiltIn) {
        def = definitionIndexBySlug[item.key!];
    }
    const attributeType = def ? getAttributeType(def!.fieldType) : undefined;
    const formats = attributeType
        ? attributeType.getAvailableFormats({
              uiLocale: i18n.language,
              t,
          })
        : [];

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
                    {item.type === ProfileItemType.Divider ? (
                        <FormRow>
                            <TextField
                                label={t(
                                    'form.profile_item.key.label',
                                    'Value'
                                )}
                                disabled={submitting}
                                {...register('key' as any)}
                            />
                            <FormFieldErrors field={'key'} errors={errors} />
                        </FormRow>
                    ) : null}

                    {![
                        ProfileItemType.Divider,
                        ProfileItemType.Spacer,
                    ].includes(item.type) ? (
                        <FormRow>
                            <SwitchWidget
                                control={control}
                                name={'displayEmpty'}
                                label={t(
                                    'form.profile_item.display_empty.label',
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
                                    'form.profile_item.format.label',
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
                                            'form.profile_item.format.none',
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

                <Button
                    type={'submit'}
                    loading={submitting}
                    disabled={submitting}
                    variant={'contained'}
                >
                    {t('form.profile_item.save', 'Save')}
                </Button>
                <RemoteErrors errors={remoteErrors} />
            </Paper>
        </form>
    );
}
