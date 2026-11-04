'use client';

import type {MouseEventHandler, ReactNode} from 'react';
import Link from 'next/link';
import {useTranslation} from 'react-i18next';
import {
    LanguagesIcon,
    LayoutGridIcon,
    LogInIcon,
    LogOutIcon,
    SettingsIcon,
    UserIcon,
} from 'lucide-react';
import {Button, type ButtonProps} from '../ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '../ui/menu';
import {Avatar} from '../ui/misc';
import {Tooltip} from '../ui/overlays';
import {cn} from '../utils/cn';
import {parseInlineStyle} from '../utils/style';

/*
 * The elements of the top bar every client shows: the logo, the link to the
 * other services, the settings and user menus. They hold no state of their
 * own: the client passes what it knows (configuration, session…).
 */

export type AppLogoSource = {
    src?: string;
    /** Inline CSS declarations set by the configurator next to the logo */
    style?: string;
};

/**
 * The logo of the organisation when the stack defines one, else `icon` in a
 * tile, then the name of the application. A link when `href` is given.
 */
export function AppLogo({
    name,
    icon,
    logo,
    href,
    onClick,
    className,
    nameClassName = 'hidden sm:inline',
    ...props
}: {
    'name': string;
    'icon': ReactNode;
    'logo'?: AppLogoSource;
    'href'?: string;
    'onClick'?: MouseEventHandler<HTMLAnchorElement>;
    'className'?: string;
    'nameClassName'?: string;
    'data-testid'?: string;
}) {
    const content = (
        <>
            {logo?.src ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img
                    src={logo.src}
                    alt={name}
                    className="h-6"
                    style={parseInlineStyle(logo.style)}
                />
            ) : (
                <span className="flex size-7 shrink-0 items-center justify-center rounded-md bg-primary text-primary-foreground [&_svg]:size-4">
                    {icon}
                </span>
            )}
            <span className={nameClassName}>{name}</span>
        </>
    );
    const classes = cn(
        'flex min-w-0 shrink-0 items-center gap-2 rounded-md px-2 py-1 font-semibold',
        href && 'hover:bg-accent',
        className
    );

    return href ? (
        <Link
            href={href}
            onClick={onClick}
            className={classes}
            data-testid={props['data-testid']}
        >
            {content}
        </Link>
    ) : (
        <span className={classes} data-testid={props['data-testid']}>
            {content}
        </span>
    );
}

/** The way to the dashboard listing every service of the stack */
export function ServicesMenuLink({
    url,
    newTab = true,
}: {
    url: string;
    newTab?: boolean;
}) {
    const {t} = useTranslation();
    const label = t('nav.dashboard', 'All services');

    return (
        <Tooltip content={label}>
            <Button variant="ghost" size="icon-sm" asChild>
                <a
                    href={url}
                    data-testid="services-link"
                    aria-label={label}
                    {...(newTab
                        ? {target: '_blank', rel: 'noopener noreferrer'}
                        : {})}
                >
                    <LayoutGridIcon />
                </a>
            </Button>
        </Tooltip>
    );
}

/** The settings menu: its items are the `children` (theme, language…) */
export function SettingsMenu({
    children,
    className = 'w-56',
}: {
    children: ReactNode;
    className?: string;
}) {
    const {t} = useTranslation();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    data-testid="settings-menu"
                    aria-label={t('nav.settings', 'Settings')}
                >
                    <SettingsIcon />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className={className}
                // An item opened a dialog, which took the focus: giving it
                // back to the trigger once the menu has faded out would
                // steal it (and the next key reopen the menu)
                onCloseAutoFocus={e => {
                    if (document.activeElement?.closest('[role=dialog]')) {
                        e.preventDefault();
                    }
                }}
            >
                {children}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/**
 * The "Language" sub-menu of the settings menu: switches the language of
 * i18next, then calls `onChange`. The `children` items follow the list.
 */
export function LanguageMenu({
    languages,
    onChange,
    children,
}: {
    languages: readonly {code: string; label: string}[];
    onChange?: (lng: string) => void;
    children?: ReactNode;
}) {
    const {t, i18n} = useTranslation();

    return (
        <DropdownMenuSub>
            <DropdownMenuSubTrigger data-testid="language-menu">
                <LanguagesIcon /> {t('settings.language', 'Language')}
            </DropdownMenuSubTrigger>
            <DropdownMenuSubContent>
                <DropdownMenuRadioGroup
                    value={i18n.language}
                    onValueChange={lng => {
                        void i18n.changeLanguage(lng);
                        onChange?.(lng);
                    }}
                >
                    {languages.map(l => (
                        <DropdownMenuRadioItem
                            key={l.code}
                            value={l.code}
                            data-testid={`language-${l.code}`}
                        >
                            {l.label}
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
                {children}
            </DropdownMenuSubContent>
        </DropdownMenuSub>
    );
}

export type MenuUser = {
    username: string;
    name?: string;
    email?: string;
};

/**
 * The user menu: the avatar opening the identity of the user, the link to the
 * account managed by the identity provider and the sign out — or the sign in
 * button without a session. `details` go under the identity, `leadingItems`
 * before the account link and `children` between it and the sign out.
 */
export function UserMenu({
    user,
    loading,
    redirecting,
    accountUrl,
    onLogin,
    onLogout,
    signInVariant,
    details,
    leadingItems,
    children,
}: {
    /** Signed in user, if any */
    user: MenuUser | null | undefined;
    /** The session is not known yet: nothing to offer */
    loading?: boolean;
    /** The sign in is redirecting to the identity provider */
    redirecting?: boolean;
    accountUrl: () => string;
    onLogin: () => void;
    onLogout: () => void;
    signInVariant?: ButtonProps['variant'];
    details?: ReactNode;
    leadingItems?: ReactNode;
    children?: ReactNode;
}) {
    const {t} = useTranslation();

    if (loading) {
        return <span className="size-8" />;
    }

    if (!user) {
        return (
            <Button
                size="sm"
                variant={signInVariant}
                data-testid="sign-in"
                loading={redirecting}
                onClick={() => onLogin()}
            >
                <LogInIcon /> {t('user.login', 'Sign in')}
            </Button>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    data-testid="user-menu"
                    className="rounded-full focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none"
                    aria-label={user.username}
                >
                    <Avatar name={user.name ?? user.username} size="md" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel className="truncate text-sm text-foreground">
                    {user.name ?? user.username}
                    {user.email ? (
                        <div className="truncate text-xs font-normal text-muted-foreground">
                            {user.email}
                        </div>
                    ) : null}
                    {details}
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                {leadingItems}
                <DropdownMenuItem asChild>
                    <a href={accountUrl()}>
                        <UserIcon /> {t('user.account', 'My account')}
                    </a>
                </DropdownMenuItem>
                {children}
                <DropdownMenuItem
                    data-testid="logout"
                    onSelect={() => onLogout()}
                >
                    <LogOutIcon /> {t('user.logout', 'Sign out')}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
