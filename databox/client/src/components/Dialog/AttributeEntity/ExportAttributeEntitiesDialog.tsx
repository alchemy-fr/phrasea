import {AttributeEntity} from '../../../types.ts';
import {useTranslation} from 'react-i18next';
import {AppDialog} from '@alchemy/phrasea-ui';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {NO_LOCALE} from '../../Media/Asset/Attribute/constants.ts';
import {Box, Button} from '@mui/material';
import {useState} from 'react';
import {jsonFormatter} from './formatters/jsonFormatter.ts';
import {csvFormatter} from './formatters/csvFormatter.ts';
import {xmlFormatter} from './formatters/xmlFormatter.ts';
import {liFormFormatter} from './formatters/liFormFormatter.ts';
import {RSelectWidget} from '@alchemy/react-form';
import CopyToClipboard from '../../../lib/CopyToClipboard.tsx';
import ContentCopyIcon from '@mui/icons-material/ContentCopy';

type Props = {
    list: AttributeEntity[];
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
            formatter: liFormFormatter,
        },
        {
            value: 'json',
            label: 'JSON',
            formatter: jsonFormatter,
        },
        {
            value: 'csv',
            label: 'CSV',
            formatter: csvFormatter,
        },
        {
            value: 'xml',
            label: 'XML',
            formatter: xmlFormatter,
        },
    ];

    const {t} = useTranslation();
    const {closeModal} = useModals();
    const [locale, setLocale] = useState<string>(NO_LOCALE);
    const [format, setFormat] = useState<string>(formatOptions[0].value);
    const code = formatOptions
        .find(opt => opt.value === format)!
        .formatter(list, NO_LOCALE === locale, locale);

    return (
        <AppDialog
            maxWidth={'md'}
            modalIndex={modalIndex}
            onClose={() => {
                closeModal();
            }}
            title={t(
                'admin:dialog.export_attribute_entities.title',
                'Export Attribute Entities'
            )}
            open={open}
            actions={({onClose}) => (
                <>
                    <CopyToClipboard>
                        {({copy}) => (
                            <Button
                                variant={'contained'}
                                onClick={() => copy(code)}
                                startIcon={<ContentCopyIcon />}
                            >
                                {t(
                                    'admin:dialog.export_attribute_entities.copy_code',
                                    'Copy Code'
                                )}
                            </Button>
                        )}
                    </CopyToClipboard>
                    <Button onClick={onClose} sx={{ml: 1}}>
                        {t('dialog.close', 'Close')}
                    </Button>
                </>
            )}
        >
            <Box
                sx={{
                    display: 'flex',
                    gap: 2,
                    mb: 2,
                }}
            >
                <div>
                    <RSelectWidget
                        label={t(
                            'admin:dialog.export_attribute_entities.locale',
                            'Locale'
                        )}
                        value={locale as any}
                        placeholder={t(
                            'admin:dialog.export_attribute_entities.select_locale',
                            'Select Locale'
                        )}
                        isClearable={false}
                        required={true}
                        onChange={newValue => setLocale(newValue!.value)}
                        options={[
                            {
                                value: NO_LOCALE,
                                label: t(
                                    'admin:dialog.export_attribute_entities.all_locales',
                                    'All Locales'
                                ),
                            },
                            ...locales.map(loc => ({
                                value: loc,
                                label: loc,
                            })),
                        ]}
                    />
                </div>
                <div>
                    <RSelectWidget
                        label={t(
                            'admin:dialog.export_attribute_entities.format',
                            'Format'
                        )}
                        required={true}
                        value={format as any}
                        placeholder={t(
                            'admin:dialog.export_attribute_entities.select_format',
                            'Select Format'
                        )}
                        isClearable={false}
                        onChange={newValue => setFormat(newValue!.value)}
                        options={formatOptions.map(opt => ({
                            value: opt.value,
                            label: opt.label,
                        }))}
                    />
                </div>
            </Box>
            <pre>{code}</pre>
        </AppDialog>
    );
}
