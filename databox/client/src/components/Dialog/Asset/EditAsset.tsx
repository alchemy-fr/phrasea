import {Asset, Tag} from '../../../types';
import {useTranslation} from 'react-i18next';
import {toast} from 'react-toastify';
import {useFormSubmit} from '@alchemy/api';
import FormTab, {useDirtyFormPrompt} from '../Tabbed/FormTab';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import {AssetApiInput, putAsset} from '../../../api/asset';
import {Privacy} from '../../../api/privacy.ts';
import {FormRow} from '@alchemy/react-form';
import {FormGroup, InputLabel, TextField} from '@mui/material';
import {FormFieldErrors} from '@alchemy/react-form';
import TagSelect from '../../Form/TagSelect.tsx';
import PrivacyField from '../../Ui/PrivacyField.tsx';

type Props = {
    id: string;
    data: Asset;
} & DialogTabProps;

export default function EditAsset({data, onClose, minHeight}: Props) {
    const {t} = useTranslation();

    const formId = 'edit-asset';

    const {
        register,
        control,
        formState: {errors},
        submitting,
        handleSubmit,
        remoteErrors,
        forbidNavigation,
    } = useFormSubmit<Asset>({
        defaultValues: data
            ? {
                  title: data.title,
                  privacy: data.privacy,
                  tags: (data?.tags?.map(t => t['@id']) ??
                      []) as unknown as Tag[],
              }
            : {
                  title: '',
                  privacy: Privacy.Secret,
                  tags: [] as Tag[],
              },
        onSubmit: async d => {
            return await putAsset(data.id, d as unknown as AssetApiInput);
        },
        onSuccess: () => {
            toast.success(
                t('form.asset_edit.success', 'Asset edited!') as string
            );
            onClose();
        },
    });

    useDirtyFormPrompt(forbidNavigation);

    return (
        <FormTab
            onClose={onClose}
            formId={formId}
            loading={submitting}
            errors={remoteErrors}
            minHeight={minHeight}
        >
            <form id={formId} onSubmit={handleSubmit}>
                <FormRow>
                    <TextField
                        autoFocus
                        required={true}
                        label={t('form.asset.title.label', 'Title')}
                        disabled={submitting}
                        {...register('title', {
                            required: true,
                        })}
                    />
                    <FormFieldErrors field={'title'} errors={errors} />
                </FormRow>
                <FormRow>
                    <FormGroup>
                        <InputLabel>
                            {t('form.asset.tags.label', 'Tags')}
                        </InputLabel>
                        <TagSelect
                            workspaceId={data.workspace.id}
                            control={control}
                            name={'tags'}
                        />
                        <FormFieldErrors<Asset>
                            field={'tags'}
                            errors={errors}
                        />
                    </FormGroup>
                </FormRow>
                <FormRow>
                    <PrivacyField control={control} name={'privacy'} />
                </FormRow>
            </form>
        </FormTab>
    );
}
