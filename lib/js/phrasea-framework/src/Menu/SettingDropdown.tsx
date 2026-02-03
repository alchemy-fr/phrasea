import {useModals} from '@alchemy/navigation';
import {DashboardMenu, DropdownActions} from '@alchemy/phrasea-ui';
import {ListItemIcon, ListItemText, MenuItem} from '@mui/material';
import {SettingDropdownProps} from './types';
import {LocaleIcon} from '../Locale/LocaleIcon';
import {useTranslation} from 'react-i18next';
import {getBestLocale} from '@alchemy/i18n/src/Locale/localeHelper';
import LocaleDialog from '../Locale/LocaleDialog';
import {useContext} from 'react';
import ThemeEditorContext from '../Theme/ThemeEditor/ThemeEditorContext';
import {rootDefaultLocale} from '@alchemy/i18n';
import ThemeEditor from '../Theme/ThemeEditor/ThemeEditor';
import ColorLensIcon from '@mui/icons-material/ColorLens';

export default function SettingDropdown({
    mainButton,
    appLocales,
    defaultLocale = rootDefaultLocale,
    ChangeThemeDialog,
    config,
    LocaleDialogComponent = LocaleDialog,
    anchorOrigin,
    transformOrigin,
}: SettingDropdownProps) {
    const themeEditorContext = useContext(ThemeEditorContext);
    const {t, i18n} = useTranslation();
    const {openModal} = useModals();

    const currentLocale =  getBestLocale(appLocales ?? [], i18n.language ? [i18n.language] : [])! ??
        defaultLocale;

    return (
        <>
            <DropdownActions
                mainButton={mainButton}
                keepMounted
                anchorOrigin={
                    anchorOrigin ?? {
                        vertical: 'top',
                        horizontal: 'right',
                    }
                }
                transformOrigin={
                    transformOrigin ?? {
                        vertical: 'top',
                        horizontal: 'left',
                    }
                }
            >
                {closeWrapper => [
                    appLocales ? (
                        <MenuItem
                            key={'change_locale'}
                            onClick={closeWrapper(() => {
                                openModal(LocaleDialogComponent, {
                                    appLocales,
                                });
                            })}
                        >
                            <ListItemIcon>
                                <LocaleIcon
                                    locale={currentLocale}
                                    height="25"
                                />
                            </ListItemIcon>
                            <ListItemText
                                primary={t(
                                    'framework.locale.current',
                                    'English'
                                )}
                            />
                        </MenuItem>
                    ) : null,
                    ChangeThemeDialog ? (
                        <MenuItem
                            key={'change_theme'}
                            onClick={closeWrapper(() => {
                                openModal(ChangeThemeDialog);
                            })}
                        >
                            <ListItemIcon>
                                <ColorLensIcon />
                            </ListItemIcon>
                            <ListItemText
                                primary={t(
                                    'framework.menu.change_theme',
                                    'Change theme'
                                )}
                            />
                        </MenuItem>
                    ) : null,
                    <MenuItem
                        key={'theme_editor'}
                        onClick={closeWrapper(() => {
                            openModal(
                                ThemeEditor,
                                {},
                                {
                                    forwardedContexts: [
                                        {
                                            context: ThemeEditorContext,
                                            value: themeEditorContext,
                                        },
                                    ],
                                }
                            );
                        })}
                    >
                        <ListItemIcon>
                            <ColorLensIcon />
                        </ListItemIcon>
                        <ListItemText
                            primary={t(
                                'framework.menu.theme_editor',
                                'Theme Editor'
                            )}
                        />
                    </MenuItem>,
                    config.displayServicesMenu ? (
                        <DashboardMenu
                            key={'services_menu'}
                            dashboardBaseUrl={config.dashboardBaseUrl}
                            children={({icon, open, onClick, ...props}) => {
                                return (
                                    <MenuItem
                                        selected={open}
                                        style={{
                                            color: 'inherit',
                                        }}
                                        {...props}
                                        onClick={onClick}
                                    >
                                        <ListItemIcon>{icon}</ListItemIcon>
                                        <ListItemText
                                            primary={t(
                                                'framework.menu.services',
                                                'Services'
                                            )}
                                        />
                                    </MenuItem>
                                );
                            }}
                        />
                    ) : null,
                ]}
            </DropdownActions>
        </>
    );
}
