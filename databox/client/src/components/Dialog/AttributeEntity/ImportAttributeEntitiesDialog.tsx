import {EntityList} from '../../../types.ts';
import {useTranslation} from 'react-i18next';
import {AppDialog} from '@alchemy/phrasea-ui';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {Button, TextField} from '@mui/material';
import {useFormSubmit} from '@alchemy/api';
import {RemoteErrors} from '@alchemy/react-form';
import {importEntities} from '../../../api/entityList.ts';

type Props = {
    list: EntityList;
    onSuccess: () => void;
} & StackedModalProps;

type FormData = {
    values: string;
};

export default function ImportAttributeEntitiesDialog({
    open,
    modalIndex,
    list,
    onSuccess,
}: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();

    const formId = 'import-attribute-entities-form';
    const {handleSubmit, register, remoteErrors, submitting} =
        useFormSubmit<FormData>({
            defaultValues: {
                values: '',
            },
            onSubmit: async data => {
                const entries = data.values
                    .split('\n')
                    .map(line => line.trim())
                    .filter(line => line.length > 0);

                await importEntities(list.id, entries);

                return data;
            },
            toastSuccess: t(
                'dialog.import_attribute_entities.toast.success',
                'Attribute entities imported successfully.'
            ),
            onSuccess: () => {
                closeModal();
                onSuccess();
            },
        });

    return (
        <AppDialog
            maxWidth={'md'}
            modalIndex={modalIndex}
            onClose={closeModal}
            title={t('dialog.import_attribute_entities.title', {
                defaultValue: 'Import Attribute Entities to {{list}}',
                list: list.name,
            })}
            open={open}
            actions={({onClose}) => (
                <>
                    <Button onClick={onClose} disabled={submitting}>
                        {t('dialog.cancel', 'Cancel')}
                    </Button>
                    <Button
                        type={'submit'}
                        disabled={submitting}
                        loading={submitting}
                        form={formId}
                        variant={'contained'}
                    >
                        {t(
                            'dialog.import_attribute_entities.import_button',
                            'Import'
                        )}
                    </Button>
                </>
            )}
        >
            <form onSubmit={handleSubmit} id={formId}>
                <TextField
                    label={t(
                        'dialog.export_attribute_entities.values.label',
                        'Values'
                    )}
                    helperText={t(
                        'dialog.export_attribute_entities.values.helper_text',
                        'Enter one attribute entity value per line.'
                    )}
                    fullWidth={true}
                    multiline={true}
                    minRows={20}
                    {...register('values')}
                />
                <RemoteErrors errors={remoteErrors} />
            </form>
        </AppDialog>
    );
}
