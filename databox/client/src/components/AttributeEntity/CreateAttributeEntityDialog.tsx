import {AttributeEntity} from '../../types.ts';
import {useTranslation} from 'react-i18next';
import {AppDialog} from '@alchemy/phrasea-ui';
import {
    StackedModalProps,
    useInRouterDirtyFormPrompt,
    useModals,
} from '@alchemy/navigation';
import {Button, TextField} from '@mui/material';
import {LoadingButton} from '@mui/lab';
import {FormFieldErrors, FormRow, KeyTranslationsWidget, getNonEmptyTranslations} from '@alchemy/react-form';
import {postAttributeEntity} from '../../api/attributeEntity.ts';
import {toast} from 'react-toastify';
import {useFormSubmit} from '@alchemy/api';
import RemoteErrors from '../Form/RemoteErrors.tsx';
import Flag from "../Ui/Flag.tsx";

type Props = {
    value: string;
    type: string;
    workspaceId: string;
    onCreate: (entity: AttributeEntity) => void;
} & StackedModalProps;

export default function CreateAttributeEntityDialog({
    open,
    modalIndex,
    value,
    type,
    workspaceId,
    onCreate,
}: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();
    const formId = 'attr-entity';

    const {
        submitting,
        remoteErrors,
        forbidNavigation,
        handleSubmit,
        register,
        formState: {errors},
    } = useFormSubmit<AttributeEntity>({
        defaultValues: {
            value,
        },
        onSubmit: async data => {
            const d = {
                ...data,
                translations: getNonEmptyTranslations(data.translations ?? {})
            };

            return await postAttributeEntity(workspaceId, {
                ...d,
                type,
            });
        },
        onSuccess: data => {
            onCreate(data);

            toast.success(
                t('attribute_entity.form.created', 'Item created!') as string
            );
            closeModal();
        },
    });

    useInRouterDirtyFormPrompt(t, forbidNavigation);

    return (
        <AppDialog
            onClose={closeModal}
            open={open}
            modalIndex={modalIndex}
            title={t('attribute_entity.dialog.create.title', 'New Item')}
            actions={({onClose}) => (
                <>
                    <Button onClick={onClose}>
                        {t('common.cancel', 'Cancel')}
                    </Button>
                    <LoadingButton
                        loading={submitting}
                        disabled={submitting}
                        variant={'contained'}
                        form={formId}
                        type={'submit'}
                        color={'primary'}
                    >
                        {t('common.save', 'Save')}
                    </LoadingButton>
                </>
            )}
        >
            <form id={formId} onSubmit={handleSubmit}>
                <FormRow>
                    <TextField
                        autoFocus
                        required={true}
                        label={t('form.attribute_entity.value.label', 'Value')}
                        disabled={submitting}
                        {...register('value', {
                            required: true,
                        })}
                    />
                    <FormFieldErrors field={'value'} errors={errors} />
                </FormRow>

                <FormRow>
                    <KeyTranslationsWidget
                        renderLocale={l => {
                            return <Flag
                                sx={{
                                    mr: 1,
                                }}
                                locale={l}
                            />
                        }}
                        locales={['en', 'fr', 'it']} // TODO set workspace locales
                        name={'translations'}
                        errors={errors}
                        register={register}
                    />
                </FormRow>

                <RemoteErrors errors={remoteErrors} />
            </form>
        </AppDialog>
    );
}
