import {EntityList} from '../../../types.ts';
import {useTranslation} from 'react-i18next';
import {AppDialog} from '@alchemy/phrasea-ui';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {NO_LOCALE} from '../../Media/Asset/Attribute/constants.ts';
import {Button} from '@mui/material';
import {FormRow, RemoteErrors, RSelectWidget} from '@alchemy/react-form';
import {exportEntities} from '../../../api/entityList.ts';
import FileDownloadIcon from '@mui/icons-material/FileDownload';
import {useFormSubmit} from '@alchemy/api';

type FormData = {
    format: string;
    locale: string;
};

type Props = {
    list: EntityList;
    locales: string[];
} & StackedModalProps;

export default function ExportAttributeEntitiesDialog({
    open,
    modalIndex,
    list,
    locales,
}: Props) {
    const formatOptions = [
        {
            value: 'liform',
            label: 'LiForm (Uploader)',
        },
        {
            value: 'json',
            label: 'JSON',
        },
        {
            value: 'csv',
            label: 'CSV',
        },
    ];

    const {t} = useTranslation();
    const {closeModal} = useModals();

    const formId = 'export-attribute-entities-form';
    const {handleSubmit, remoteErrors, submitting, control} =
        useFormSubmit<FormData>({
            defaultValues: {
                format: '',
                locale: NO_LOCALE,
            },
            onSubmit: async data => {
                await exportEntities(list.id, data);

                return data;
            },
            onSuccess: () => {
                closeModal();
            },
        });

    return (
        <AppDialog
            maxWidth={'md'}
            modalIndex={modalIndex}
            onClose={closeModal}
            title={t('dialog.export_attribute_entities.title', {
                defaultValue: 'Export {{list}}',
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
                        startIcon={<FileDownloadIcon />}
                    >
                        {t('dialog.export_attribute_entities.export', 'Export')}
                    </Button>
                </>
            )}
        >
            <form onSubmit={handleSubmit} id={formId}>
                <FormRow>
                    <RSelectWidget
                        control={control}
                        name={'format'}
                        label={t(
                            'dialog.export_attribute_entities.format',
                            'Format'
                        )}
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
                </FormRow>
                <FormRow>
                    <RSelectWidget
                        control={control}
                        name={'locale'}
                        label={t(
                            'dialog.export_attribute_entities.locale',
                            'Locale'
                        )}
                        placeholder={t(
                            'dialog.export_attribute_entities.select_locale',
                            'Select Locale'
                        )}
                        isClearable={false}
                        required={true}
                        options={[
                            {
                                value: NO_LOCALE,
                                label: t(
                                    'dialog.export_attribute_entities.all_locales',
                                    'All Locales'
                                ),
                            },
                            ...locales.map(loc => ({
                                value: loc,
                                label: loc,
                            })),
                        ]}
                    />
                </FormRow>
                <RemoteErrors errors={remoteErrors} />
            </form>
        </AppDialog>
    );
}
