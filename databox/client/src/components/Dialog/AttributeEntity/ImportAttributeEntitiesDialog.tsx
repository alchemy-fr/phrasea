import {EntityList} from '../../../types.ts';
import {useTranslation} from 'react-i18next';
import {AppDialog} from '@alchemy/phrasea-ui';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {Box, Button, TextField} from '@mui/material';
import {useFormSubmit} from '@alchemy/api';
import {RemoteErrors, RSelectWidget} from '@alchemy/react-form';
import {importEntities} from '../../../api/entityList.ts';

type Props = {
    list: EntityList;
    onSuccess: () => void;
} & StackedModalProps;

type FormData = {
    data: string;
    format: string;
};

export default function ImportAttributeEntitiesDialog({
    open,
    modalIndex,
    list,
    onSuccess,
}: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();

    const formatOptions = [
        {
            value: 'raw',
            label: t(
                'attribute_entity.import.format.raw',
                'Raw (One value per line)'
            ),
        },
        {
            value: 'csv',
            label: t('attribute_entity.import.format.csv', 'CSV'),
        },
    ];

    const formId = 'import-attribute-entities-form';
    const {handleSubmit, register, remoteErrors, submitting, control} =
        useFormSubmit<FormData>({
            defaultValues: {
                format: formatOptions[0].value,
                data: '',
            },
            onSubmit: async data => {
                await importEntities(list.id, data.format, data.data);

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
                <Box
                    sx={{
                        display: 'flex',
                        gap: 2,
                        mb: 2,
                    }}
                >
                    <div>
                        <RSelectWidget
                            control={control}
                            label={t(
                                'dialog.export_attribute_entities.format',
                                'Format'
                            )}
                            name={'format'}
                            required={true}
                            placeholder={t(
                                'dialog.export_attribute_entities.select_format',
                                'Select Format'
                            )}
                            isClearable={false}
                            options={formatOptions.map(opt => ({
                                value: opt.value,
                                label: opt.label,
                            }))}
                        />
                    </div>
                </Box>

                <TextField
                    label={t(
                        'dialog.export_attribute_entities.data.label',
                        'Data'
                    )}
                    fullWidth={true}
                    multiline={true}
                    minRows={20}
                    {...register('data')}
                    slotProps={{
                        input: {
                            style: {
                                fontFamily: 'Monospace, monospace',
                            },
                        },
                    }}
                />
                <RemoteErrors errors={remoteErrors} />
            </form>
        </AppDialog>
    );
}
