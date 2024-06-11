import {TextField} from '@mui/material';
import FormDialog from '../../../Dialog/FormDialog';
import FileCopyIcon from '@mui/icons-material/FileCopy';
import RemoteErrors from '../../../Form/RemoteErrors';
import {FormFieldErrors, FormRow} from '@alchemy/react-form';
import {useTranslation} from 'react-i18next';
import {useFormSubmit} from '@alchemy/api';
import {
    StackedModalProps,
    useModals,
    useOutsideRouterDirtyFormPrompt,
} from '@alchemy/navigation';
import {Basket, IntegrationData} from '../../../../types.ts';
import {runIntegrationAction} from '../../../../api/integrations.ts';
import SwitchWidget from '../../../Form/SwitchWidget.tsx';
import ExposeProfileSelect from './ExposeProfileSelect.tsx';
import {ExposePublication} from './exposeType.ts';
import ExposePublicationSelect from './ExposePublicationSelect.tsx';

type Props = {
    integrationId: string;
    basket: Basket;
    onSuccess: (data: IntegrationData) => void;
} & StackedModalProps;

type FormData = Omit<ExposePublication, 'id'>;

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
        control,
        formState: {errors},
        handleSubmit,
        remoteErrors,
        submitting,
        forbidNavigation,
    } = useFormSubmit({
        defaultValues: {
            title: basket.title,
            slug: '',
            description: basket.description,
            enabled: true,
            profile: null,
            parent: null,
        },
        onSubmit: async ({
            title,
            description,
            profile,
            parent,
            slug,
            ...config
        }: FormData) => {
            return await runIntegrationAction('sync', integrationId, {
                basketId: basket.id,
                title,
                description,
                profile,
                parent,
                slug: slug || null,
                config,
            });
        },
        toastSuccess: t(
            'integration.expose.create_pub.success',
            `Publication has been created and will be synced`
        ),
        onSuccess: (d: IntegrationData) => {
            onSuccess(d);
            closeModal();
        },
    });
    useOutsideRouterDirtyFormPrompt(t, forbidNavigation, modalIndex);

    const formId = 'ep';

    return (
        <FormDialog
            title={t(
                'integration.expose.create_pub.title',
                `Create Publication`
            )}
            open={open}
            modalIndex={modalIndex}
            loading={submitting}
            formId={formId}
            submitIcon={<FileCopyIcon />}
            submitLabel={t(
                'integration.expose.create_pub.submit',
                `Create & Sync`
            )}
        >
            <form id={formId} onSubmit={handleSubmit}>
                <FormRow>
                    <ExposePublicationSelect
                        label={t(
                            'integration.expose.form.parent',
                            'Parent Publication'
                        )}
                        control={control}
                        name={'parent'}
                        integrationId={integrationId}
                    />
                </FormRow>
                <FormRow>
                    <TextField
                        autoFocus
                        required={true}
                        label={t(
                            'integration.expose.form.title',
                            'Publication Title'
                        )}
                        disabled={submitting}
                        {...register('title', {
                            required: true,
                        })}
                    />
                    <FormFieldErrors field={'title'} errors={errors} />
                </FormRow>
                <FormRow>
                    <TextField
                        label={t('integration.expose.form.slug', 'Identitier')}
                        disabled={submitting}
                        {...register('slug')}
                    />
                    <FormFieldErrors field={'slug'} errors={errors} />
                </FormRow>
                <FormRow>
                    <TextField
                        label={t(
                            'integration.expose.form.description',
                            'Description'
                        )}
                        disabled={submitting}
                        style={{width: '100%'}}
                        {...register('description')}
                        multiline={true}
                    />
                    <FormFieldErrors field={'description'} errors={errors} />
                </FormRow>
                <FormRow>
                    <SwitchWidget
                        control={control}
                        name={'enabled'}
                        label={t('integration.expose.form.enabled', 'Enabled')}
                    />
                </FormRow>
                <FormRow>
                    <ExposeProfileSelect
                        label={t(
                            'integration.expose.form.profile',
                            'Publication Profile'
                        )}
                        control={control}
                        name={'profile'}
                        integrationId={integrationId}
                    />
                </FormRow>
            </form>
            <RemoteErrors errors={remoteErrors} />
        </FormDialog>
    );
}
