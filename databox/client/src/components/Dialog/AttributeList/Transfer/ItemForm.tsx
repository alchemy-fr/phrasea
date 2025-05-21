import {InputLabel, List, ListItem, ListItemAvatar, ListItemIcon, ListItemText, TextField} from "@mui/material";
import {useFormSubmit} from '@alchemy/api';
import {AttributeListItem, AttributeListItemType} from "../../../../types.ts";
import {toast} from "react-toastify";
import {useDirtyFormPrompt} from "../../Tabbed/FormTab.tsx";
import {useTranslation} from 'react-i18next';
import {FormFieldErrors, FormRow, SwitchWidget} from '@alchemy/react-form';
import {putAttributeListItem} from "../../../../api/attributeList.ts";
import RemoteErrors from "../../../Form/RemoteErrors.tsx";
import {LoadingButton} from "@mui/lab";
import {getAttributeType} from "../../../Media/Asset/Attribute/types";
import {AttributeDefinitionsIndex} from "../../../../store/attributeDefinitionStore.ts";
import {useAttributeListStore} from "../../../../store/attributeListStore.ts";

type Props = {
    item: AttributeListItem;
    listId: string;
    onChange: (item: AttributeListItem) => void;
    definitionsIndex: AttributeDefinitionsIndex;
};

export default function ItemForm({
    item,
    definitionsIndex,
    listId,
    onChange,
}: Props) {
    const {t} = useTranslation();
    const updateAttributeListItem = useAttributeListStore(state => state.updateAttributeListItem);

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
        onSuccess: (data) => {
            updateAttributeListItem(listId, data);
            onChange(data);
            toast.success(
                t('form.attribute_list_item.success', 'Item saved!') as string
            );
        },
    });

    useDirtyFormPrompt(forbidNavigation);
    const formId = 'attr-list-item-basket';

    const def = item.definition ? definitionsIndex[item.definition] : undefined;
    const attributeType = def ? getAttributeType(def!.fieldType) : undefined;
    const formats = attributeType ? attributeType.getAvailableFormats() : [];

    return <form id={formId} onSubmit={handleSubmit}>
        {item.type === AttributeListItemType.Divider ? <FormRow>
            <TextField
                label={t('form.attribute_list_item.key.label', 'Value')}
                disabled={submitting}
                {...register('key' as any)}
            />
            <FormFieldErrors field={'key'} errors={errors}/>
        </FormRow> : null}

        {![
            AttributeListItemType.Divider,
            AttributeListItemType.Spacer,
        ].includes(item.type) ? <FormRow>
            <SwitchWidget
                control={control}
                name={'displayEmpty'}
                label={t('form.attribute_list_item.display_empty.label', 'Display even if empty')}
                disabled={submitting}
            />
        </FormRow> : null}

        {formats.length > 0 && <FormRow>
            <InputLabel>{t('form.attribute_list_item.format.label', 'Default Format')}</InputLabel>
            <List sx={{
                mx: -1,
                'input': {
                    mr: 1,
                }
            }}>
            <ListItem
                component={'label'}
            >
                <input
                    type="radio"
                    {...register('format')}
                />
                <ListItemText
                    primary={t('form.attribute_list_item.format.none', 'None')}
                />
            </ListItem>
            {formats.map(f => <ListItem
                key={f.name}
                component={'label'}
            >
                <input
                    type="radio"
                    {...register('format')}
                    value={f.name}
                />
                <ListItemText
                    primary={f.title}
                    secondary={f.example}
                />
            </ListItem>)}
            </List>
        </FormRow>}

        <LoadingButton
            type={'submit'}
            loading={submitting}
            disabled={submitting}
            variant={'contained'}
        >
            {t('form.attribute_list_item.save', 'Save')}
        </LoadingButton>
        <RemoteErrors errors={remoteErrors}/>
    </form>
}
