import {KeycloakClient} from '@alchemy/auth';
import React, {PropsWithChildren} from 'react';
import {LocaleDialogProps} from '../Locale/types';
import {NotificationUriHandler} from '@alchemy/notification';
import {DropdownActionsProps} from '@alchemy/phrasea-ui';
import {Theme} from '@mui/material';

export type CommonMenuProps = {
    config: WindowConfig;
    keycloakClient: KeycloakClient;
    notificationUriHandler?: NotificationUriHandler;
} & SettingDropdownBaseProps;

export type SettingDropdownBaseProps = {
    config: WindowConfig;
    defaultLocale?: string;
    appLocales?: string[];
    LocaleDialogComponent?: React.ComponentType<LocaleDialogProps>;
    ChangeThemeDialog?: React.ComponentType<any>;
};

export type SettingDropdownProps = {
    mainButton: DropdownActionsProps['mainButton'];
    anchorOrigin?: DropdownActionsProps['anchorOrigin'];
    transformOrigin?: DropdownActionsProps['transformOrigin'];
} & SettingDropdownBaseProps;

export type AppLogoProps = {
    appTitle: string;
    onLogoClick?: () => void;
    config: {
        logo?: {
            src?: string;
            style?: string;
        };
    };
};

export type AppMenuProps = PropsWithChildren<{
    config: WindowConfig;
    sx?: React.CSSProperties | ((theme: Theme) => React.CSSProperties);
    commonMenuProps: Omit<CommonMenuProps, 'config'>;
    logoProps: Omit<AppLogoProps, 'config'>;
}>;

export enum MenuClasses {
    PageHeader = 'Menu-PageHeader',
}
