'use client';

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
import {Button} from '@/components/ui/button';
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
} from '@/components/ui/menu';
import {Avatar} from '@/components/ui/misc';
import {useAuth} from '@/lib/auth/AuthProvider';
import {useConfig} from '@/lib/config/ConfigProvider';
import {getAuthClient} from '@/lib/auth/client';
import {supportedLanguages} from '@/i18n';
import {routes} from '@/lib/routes';
import {parseInlineStyle} from '@/lib/utils/style';
import {ThemeMenu} from '@/features/theme/ThemeMenu';

/**
 * The application header: the logo, then the settings and user menus. Not
 * displayed in the services menu of the other clients, whatever the width.
 */
export function TopBar() {
    const {t, i18n} = useTranslation();
    const {user, isAuthenticated, login, redirecting, logout} = useAuth();
    const config = useConfig();
    const title = t('app.title', 'Dashboard');

    return (
        <header
            data-testid="topbar"
            className="flex h-12 shrink-0 items-center gap-2 border-b bg-background px-2 embedded:hidden"
        >
            <Link
                href={routes.home()}
                data-testid="home-link"
                className="flex min-w-0 items-center gap-2 rounded-md px-2 py-1 font-semibold hover:bg-accent"
            >
                {config.logo ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img
                        src={config.logo.src}
                        alt=""
                        className="h-6"
                        style={parseInlineStyle(config.logo.style)}
                    />
                ) : (
                    <span className="flex size-7 shrink-0 items-center justify-center rounded-md bg-primary text-primary-foreground">
                        <LayoutGridIcon className="size-4" />
                    </span>
                )}
                <span className="truncate">{title}</span>
            </Link>

            <div className="flex-1" />

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
                <DropdownMenuContent align="end" className="w-56">
                    <ThemeMenu />
                    <DropdownMenuSub>
                        <DropdownMenuSubTrigger data-testid="language-menu">
                            <LanguagesIcon />{' '}
                            {t('settings.language', 'Language')}
                        </DropdownMenuSubTrigger>
                        <DropdownMenuSubContent>
                            <DropdownMenuRadioGroup
                                value={i18n.language}
                                onValueChange={lng =>
                                    void i18n.changeLanguage(lng)
                                }
                            >
                                {supportedLanguages.map(l => (
                                    <DropdownMenuRadioItem
                                        key={l.code}
                                        value={l.code}
                                        data-testid={`language-${l.code}`}
                                    >
                                        {l.label}
                                    </DropdownMenuRadioItem>
                                ))}
                            </DropdownMenuRadioGroup>
                        </DropdownMenuSubContent>
                    </DropdownMenuSub>
                </DropdownMenuContent>
            </DropdownMenu>

            {isAuthenticated && user ? (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <button
                            type="button"
                            data-testid="user-menu"
                            className="rounded-full focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none"
                            aria-label={user.username}
                        >
                            <Avatar
                                name={user.name ?? user.username}
                                size="md"
                            />
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
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem asChild>
                            <a
                                href={getAuthClient().getAccountUrl(
                                    typeof window !== 'undefined'
                                        ? window.location.href
                                        : '/'
                                )}
                            >
                                <UserIcon /> {t('user.account', 'My account')}
                            </a>
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            data-testid="logout"
                            onSelect={logout}
                        >
                            <LogOutIcon /> {t('user.logout', 'Sign out')}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            ) : (
                <Button
                    size="sm"
                    data-testid="sign-in"
                    loading={redirecting}
                    onClick={() => login()}
                >
                    <LogInIcon /> {t('user.login', 'Sign in')}
                </Button>
            )}
        </header>
    );
}
