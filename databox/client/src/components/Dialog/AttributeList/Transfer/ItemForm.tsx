import {Button, TextField} from "@mui/material";
import {useFormSubmit} from '@alchemy/api';
import {AttributeListItem, AttributeListItemType} from "../../../../types.ts";
import {toast} from "react-toastify";
import {useDirtyFormPrompt} from "../../Tabbed/FormTab.tsx";
import {useTranslation} from 'react-i18next';
import {FormFieldErrors, FormRow, SwitchWidget} from '@alchemy/react-form';
import {putAttributeListItem} from "../../../../api/attributeList.ts";
import RemoteErrors from "../../../Form/RemoteErrors.tsx";
import {LoadingButton} from "@mui/lab";

type Props = {
    item: AttributeListItem;
    listId: string;
};

export default function ItemForm({
    item,
    listId,
}: Props) {
    const {t} = useTranslation();

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
        onSuccess: () => {
            toast.success(
                t('form.attribute_list_item.success', 'Item saved!') as string
            );
        },
    });

    useDirtyFormPrompt(forbidNavigation);
    const formId = 'attr-list-item-basket';

    return <form id={formId} onSubmit={handleSubmit}>
        {item.type === AttributeListItemType.Divider ? <FormRow>
            <TextField
                label={t('form.attribute_list_item.key.label', 'Value')}
                disabled={submitting}
                {...register('key' as  any)}
            />
            <FormFieldErrors field={'key'} errors={errors} />
        </FormRow> : null}

        <FormRow>
            <SwitchWidget
                control={control}
                name={'displayEmpty'}
                label={t('form.attribute_list_item.display_empty.label', 'Display even if empty')}
                disabled={submitting}
            />
        </FormRow>

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
