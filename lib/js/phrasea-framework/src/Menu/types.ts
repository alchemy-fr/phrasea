import {KeycloakClient} from '@alchemy/auth';
import React from 'react';
import {LocaleDialogProps} from '../Locale/types';
import {NotificationUriHandler} from '@alchemy/notification';
import {DropdownActionsProps} from '@alchemy/phrasea-ui';

export type CommonMenuProps = {
    config: WindowConfig;
    keycloakClient: KeycloakClient;
    notificationUriHandler?: NotificationUriHandler;
} & SettingDropdownBaseProps;

export type SettingDropdownBaseProps = {
    config: WindowConfig;
    defaultLocale?: string;
    appLocales: string[];
    LocaleDialogComponent?: React.ComponentType<LocaleDialogProps>;
    ChangeThemeDialog?: React.ComponentType<any>;
};

export type SettingDropdownProps = {
    mainButton: DropdownActionsProps['mainButton'];
    anchorOrigin?: DropdownActionsProps['anchorOrigin'];
    transformOrigin?: DropdownActionsProps['transformOrigin'];
} & SettingDropdownBaseProps;
