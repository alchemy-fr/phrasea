import {
    Button,
    Chip,
    List,
    ListItemButton,
    ListItemIcon,
    ListItemText,
    ListSubheader,
    Stack,
    TextField,
} from '@mui/material';
import React from 'react';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {AppDialog} from '@alchemy/phrasea-ui';
import {useTranslation} from 'react-i18next';
import {appLocales} from '../../../translations/locales.ts';
import LocaleIcon from './LocaleIcon.tsx';
import {
    useDataLocale,
    useUpdateDataLocale,
} from '../../store/useDataLocaleStore.ts';
import {getLocales, Locale} from '../../api/locale.ts';
import ConfirmDialog from '../Ui/ConfirmDialog.tsx';

type Props = {} & StackedModalProps;

export default function LocaleDialog({open, modalIndex}: Props) {
    const {t, i18n} = useTranslation();
    const {closeModal, openModal} = useModals();
    const [locales, setLocales] = React.useState<Locale[]>();
    const [filter, setFilter] = React.useState('');

    React.useEffect(() => {
        if (open) {
            getLocales().then(data => {
                setLocales(data);
            });
        }
    }, [i18n.language]);

    const workspaceLocales = locales;

    const dataLocale = useDataLocale();
    const updateDataLocale = useUpdateDataLocale();

    const noDataLocaleId = 'no-data-locale';
    const center = () =>
        document
            .getElementById(dataLocale ?? noDataLocaleId)
            ?.scrollIntoView({block: 'center'});

    React.useEffect(() => {
        if (workspaceLocales && dataLocale) {
            setTimeout(center, 100);
        }
    }, [dataLocale, workspaceLocales]);

    if (!workspaceLocales) {
        return null;
    }

    const confirmReload = (callback: () => Promise<void>) => {
        openModal(ConfirmDialog, {
            title: t(
                'locale.switcher.change_data_locale.modal.title',
                'Page will be reloaded'
            ),
            onConfirm: async () => {
                await callback();
                window.location.reload();
            },
            confirmLabel: t(
                'locale.switcher.change_data_locale.modal.confirm',
                'Continue'
            ),
        });
    };

    const changeDataLocale = (locale: string | undefined) => {
        confirmReload(() => updateDataLocale(locale));
    };

    const changeLocale = (locale: string | undefined) => {
        const cb = async () => {
            i18n.changeLanguage(locale);
        };
        if (!dataLocale) {
            return confirmReload(cb);
        }

        cb();
    };

    return (
        <AppDialog
            modalIndex={modalIndex}
            open={open}
            title={t('locale.switcher.title', 'Change Language')}
            maxWidth={'md'}
            onClose={closeModal}
            actions={({onClose}) => (
                <>
                    <Button onClick={onClose}>
                        {t('dialog.close', 'Close')}
                    </Button>
                </>
            )}
            disablePadding
        >
            <Stack
                direction="row"
                gap={2}
                padding={2}
                justifyContent="space-between"
                sx={{
                    'height': 'calc(95vh - 200px)',
                    '> div': {
                        flex: 1,
                        overflow: 'auto',
                    },
                }}
            >
                <div>
                    <List>
                        <ListSubheader>
                            {t(
                                'locale.switcher.app_languages',
                                'App/UI Languages'
                            )}
                        </ListSubheader>
                        {appLocales.map((l: string) => (
                            <ListItemButton
                                key={l}
                                onClick={() => {
                                    changeLocale(l);
                                }}
                                selected={i18n.resolvedLanguage === l}
                            >
                                <ListItemIcon>
                                    <LocaleIcon locale={l} height="35" />
                                </ListItemIcon>
                                <ListItemText
                                    primary={t('locale.current', {
                                        lng: l,
                                        defaultValue: l.toUpperCase(),
                                    })}
                                />
                            </ListItemButton>
                        ))}
                    </List>
                </div>
                <div>
                    <List>
                        <ListSubheader>
                            <div onClick={center}>
                                {t(
                                    'locale.switcher.data_languages',
                                    'Data Language'
                                )}
                                {dataLocale ? (
                                    <Chip
                                        sx={{
                                            ml: 1,
                                        }}
                                        label={dataLocale.replace('_', '-')}
                                    />
                                ) : null}
                            </div>

                            <TextField
                                fullWidth
                                size="small"
                                type={'search'}
                                value={filter}
                                onChange={e => setFilter(e.target.value)}
                                placeholder={t(
                                    'locale.switcher.filter_placeholder',
                                    'Search...'
                                )}
                            />
                        </ListSubheader>
                        <ListItemButton
                            id={noDataLocaleId}
                            onClick={() => {
                                changeDataLocale(undefined);
                            }}
                            selected={!dataLocale}
                        >
                            <ListItemText
                                primary={t(
                                    'locale.switcher.data_locale.default',
                                    'Default'
                                )}
                                secondary={t(
                                    'locale.switcher.data_locale.default_description',
                                    'Use UI language'
                                )}
                            />
                        </ListItemButton>
                        {workspaceLocales
                            .filter(l => {
                                const sv = filter.toLowerCase();

                                return (
                                    !filter ||
                                    l.name.toLowerCase().includes(sv) ||
                                    l.nativeName.toLowerCase().includes(sv) ||
                                    l.id.toLowerCase().includes(sv)
                                );
                            })
                            .map((l: Locale) => (
                                <ListItemButton
                                    key={l.id}
                                    id={l.id}
                                    onClick={() => {
                                        changeDataLocale(l.id);
                                    }}
                                    selected={dataLocale === l.id}
                                >
                                    <ListItemText
                                        primary={l.name}
                                        secondary={l.nativeName}
                                    />
                                </ListItemButton>
                            ))}
                    </List>
                </div>
            </Stack>
        </AppDialog>
    );
}
