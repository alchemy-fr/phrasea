import {TextField} from '@mui/material';
import FormDialog from '../../../Dialog/FormDialog';
import FileCopyIcon from '@mui/icons-material/FileCopy';
import RemoteErrors from '../../../Form/RemoteErrors';
import {FormFieldErrors, FormRow} from '@alchemy/react-form';
import {useTranslation} from 'react-i18next';
import {useFormSubmit} from '@alchemy/api';
import {StackedModalProps, useModals, useOutsideRouterDirtyFormPrompt,} from '@alchemy/navigation';
import {Basket, IntegrationData} from "../../../../types.ts";
import {runIntegrationAction} from "../../../../api/integrations.ts";

type Props = {
    integrationId: string;
    basket: Basket;
    onSuccess: (data: IntegrationData) => void;
} & StackedModalProps;

type ExposePublication = {
    title: string;
    description: string;
}

export default function CreatePublicationDialog({
    open,
    modalIndex,
    basket,
    integrationId,
    onSuccess,
}: Props) {
    const {closeModal} = useModals();
    const {t} = useTranslation();

    const {
        register,
        formState: {errors},
        handleSubmit,
        remoteErrors,
        submitting,
        forbidNavigation,
    } = useFormSubmit({
        defaultValues: {
            title: basket.title,
            description: basket.description,
        },
        onSubmit: async (data: ExposePublication) => {
            return await runIntegrationAction('sync', integrationId, {
                basketId: basket.id,
                ...data
            });
        },
        toastSuccess: t('integration.expose.create_pub.success', `Publication has been created and will be synced`),
        onSuccess: (d: IntegrationData) => {
            onSuccess(d);
            closeModal();
        },
    });
    useOutsideRouterDirtyFormPrompt(t, forbidNavigation, modalIndex);

    const formId = 'ep';

    return (
        <FormDialog
            title={t('integration.expose.create_pub.title', `Create Publication`)}
            open={open}
            modalIndex={modalIndex}
            loading={submitting}
            formId={formId}
            submitIcon={<FileCopyIcon/>}
            submitLabel={t('integration.expose.create_pub.submit', `Create & Sync`)}
        >
            <form id={formId} onSubmit={handleSubmit}>
                <FormRow>
                    <TextField
                        autoFocus
                        required={true}
                        label={t('integration.expose.form.title', 'Publication Title')}
                        disabled={submitting}
                        {...register('title', {
                            required: true,
                        })}
                    />
                    <FormFieldErrors field={'title'} errors={errors}/>
                </FormRow>
                <FormRow>
                    <TextField
                        label={t('integration.expose.form.description', 'Description')}
                        disabled={submitting}
                        {...register('description')}
                        multiline={true}
                    />
                    <FormFieldErrors field={'description'} errors={errors}/>
                </FormRow>
            </form>
            <RemoteErrors errors={remoteErrors}/>
        </FormDialog>
    );
}
