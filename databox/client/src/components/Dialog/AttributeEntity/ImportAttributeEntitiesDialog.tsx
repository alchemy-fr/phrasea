import {AttributeEntity} from '../../../types.ts';
import {useTranslation} from 'react-i18next';
import {AppDialog} from '@alchemy/phrasea-ui';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {Button, TextField} from '@mui/material';
import {useFormSubmit} from '@alchemy/api';

type Props = {
    list: AttributeEntity[];
} & StackedModalProps;

type FormData = {
    values: string;
};

export default function ImportAttributeEntitiesDialog({
    open,
    modalIndex,
}: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();

    const {handleSubmit} = useFormSubmit<FormData>({
        defaultValues: {
            values: '',
        },
        onSubmit: async data => {
            // const entries = data.values
            //     .split('\n')
            //     .map(line => line.trim())
            //     .filter(line => line.length > 0);

            return data;
        },
        toastSuccess: t(
            'dialog.import_attribute_entities.toast.success',
            'Attribute entities imported successfully.'
        ),
    });

    return (
        <AppDialog
            maxWidth={'md'}
            modalIndex={modalIndex}
            onClose={() => {
                closeModal();
            }}
            title={t(
                'dialog.import_attribute_entities.title',
                'Import Attribute Entities'
            )}
            open={open}
            actions={({onClose}) => (
                <>
                    <Button onClick={onClose}>
                        {t('dialog.cancel', 'Cancel')}
                    </Button>
                    <Button onClick={onClose} variant={'contained'}>
                        {t(
                            'dialog.import_attribute_entities.import_button',
                            'Import'
                        )}
                    </Button>
                </>
            )}
        >
            <form onSubmit={handleSubmit}>
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
                />
            </form>
        </AppDialog>
    );
}
