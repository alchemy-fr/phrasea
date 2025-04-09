import {useTranslation} from 'react-i18next';
import {Asset, Share} from '../../types.ts';
import {FormFieldErrors, FormRow} from '@alchemy/react-form';
import {TextField} from '@mui/material';
import FormDialog from '../Dialog/FormDialog.tsx';
import {StackedModalProps, useModals, useFormPrompt} from '@alchemy/navigation';
import {createAssetShare} from '../../api/asset.ts';
import {useFormSubmit} from '@alchemy/api';
import RemoteErrors from '../Form/RemoteErrors.tsx';
import {dateToStringDate} from '../../lib/date.ts';

type Props = {
    asset: Asset;
    onSuccess: (share: Share) => void;
} & StackedModalProps;

export default function CreateShareDialog({
    asset,
    open,
    modalIndex,
    onSuccess,
}: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();

    const {
        register,
        formState: {errors},
        handleSubmit,
        remoteErrors,
        submitting,
        forbidNavigation,
    } = useFormSubmit<Share>({
        defaultValues: {
            title: '',
            startsAt: '',
            expiresAt: '',
        },
        onSubmit: async data => {
            return await createAssetShare(asset.id, {
                ...data,
                startsAt: dateToStringDate(data.startsAt || null),
                expiresAt: dateToStringDate(data.expiresAt || null),
            });
        },
        onSuccess: (d: Share) => {
            onSuccess(d);
            closeModal();
        },
    });
    useFormPrompt(t, forbidNavigation, modalIndex);

    const formId = 'create-share-link';

    return (
        <FormDialog
            formId={formId}
            modalIndex={modalIndex}
            open={open}
            title={t('create_share_link.dialog.title', 'Create Share Link')}
            loading={submitting}
            submitLabel={t('create_share_link.dialog.submit', 'Create Link')}
        >
            <form id={formId} onSubmit={handleSubmit}>
                <FormRow>
                    <TextField
                        autoFocus
                        label={t('create_share_link.form.title.label', 'Title')}
                        disabled={submitting}
                        {...register('title')}
                        helperText={t(
                            'create_share_link.form.title.helper',
                            'You can name the audience of this share link'
                        )}
                    />
                    <FormFieldErrors field={'title'} errors={errors} />
                </FormRow>
                <FormRow>
                    <TextField
                        InputLabelProps={{shrink: true}}
                        type={'datetime-local'}
                        label={t(
                            'create_share_link.form.startsAt.label',
                            'Starts At'
                        )}
                        disabled={submitting}
                        {...register('startsAt')}
                        helperText={t(
                            'create_share_link.form.startsAt.helper',
                            'Optional. If set, the share link will only be active after this time'
                        )}
                    />
                    <FormFieldErrors field={'startsAt'} errors={errors} />
                </FormRow>
                <FormRow>
                    <TextField
                        InputLabelProps={{shrink: true}}
                        type={'datetime-local'}
                        label={t(
                            'create_share_link.form.expiresAt.label',
                            'Expires At'
                        )}
                        disabled={submitting}
                        {...register('expiresAt')}
                        helperText={t(
                            'create_share_link.form.expiresAt.helper',
                            'Optional. If set, the share link will only be active until this time'
                        )}
                    />
                    <FormFieldErrors field={'expiresAt'} errors={errors} />
                </FormRow>
            </form>
            <RemoteErrors errors={remoteErrors} />
        </FormDialog>
    );
}
