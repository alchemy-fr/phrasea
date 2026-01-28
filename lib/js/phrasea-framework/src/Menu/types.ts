import {KeycloakClient} from '@alchemy/auth';
import React, {PropsWithChildren, ReactNode} from 'react';
import {LocaleDialogProps} from '../Locale/types';
import {NotificationUriHandler} from '@alchemy/notification';
import {DropdownActionsProps} from '@alchemy/phrasea-ui';
import {SxProps, Theme} from '@mui/material';
import {RouteDefinition, RouteParameters} from '@alchemy/navigation';
import {Location} from 'react-router-dom';

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
    sx?: SxProps<any>;
};

export type AppMenuProps = PropsWithChildren<{
    config: WindowConfig;
    sx?: React.CSSProperties | ((theme: Theme) => React.CSSProperties);
    commonMenuProps: Omit<CommonMenuProps, 'config'>;
    logoProps?: Omit<AppLogoProps, 'config'>;
    childrenSx?: SxProps<any>;
}>;

export enum MenuClasses {
    PageHeader = 'Menu-PageHeader',
}

export type NavItem = {
    id: string;
    label: ReactNode;
} & Omit<NavButtonProps, 'location'>;

export type NavButtonProps = {
    route?: RouteDefinition;
    routeParams?: RouteParameters;
    location?: Location;
};

export enum MenuOrientation {
    Horizontal = 1,
    Vertical = 2,
}
export type NavMenuProps = {
    orientation: MenuOrientation;
    items: NavItem[];
};
