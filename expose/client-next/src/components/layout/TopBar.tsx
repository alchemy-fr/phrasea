'use client';

import {type ReactNode} from 'react';
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
import {Tooltip} from '@/components/ui/overlays';
import {Avatar} from '@/components/ui/misc';
import {useAuth} from '@/lib/auth/AuthProvider';
import {getAuthClient} from '@/lib/auth/client';
import {useConfig} from '@/lib/config/ConfigProvider';
import {supportedLanguages} from '@/i18n';
import {routes} from '@/lib/routes';
import {ThemeMenu} from '@/features/theme/ThemeMenu';
import {cn} from '@/lib/utils/cn';

export function AppLogo({linked = true}: {linked?: boolean}) {
    const config = useConfig();
    const content = (
        <>
            {config.logo?.src ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img src={config.logo.src} alt="Expose" className="h-6" />
            ) : (
                <span className="flex size-7 items-center justify-center rounded-md bg-primary text-primary-foreground">
                    <LayoutGridIcon className="size-4" />
                </span>
            )}
            <span className="hidden sm:inline">Expose</span>
        </>
    );
    const className =
        'flex shrink-0 items-center gap-2 rounded-md px-2 py-1 font-semibold';

    return linked ? (
        <Link
            href={routes.home()}
            data-testid="app-logo"
            className={cn(className, 'hover:bg-accent')}
        >
            {content}
        </Link>
    ) : (
        <span data-testid="app-logo" className={className}>
            {content}
        </span>
    );
}

/**
 * The application header: an optional leading control, the logo, the
 * navigation, then the settings and user menus.
 */
export function TopBar({
    leading,
    children,
    className,
}: {
    leading?: ReactNode;
    children?: ReactNode;
    className?: string;
}) {
    const {t} = useTranslation();
    const config = useConfig();

    return (
        <header
            data-testid="topbar"
            className={cn(
                'sticky top-0 z-30 flex h-14 shrink-0 items-center gap-2 border-b bg-background/95 px-2 backdrop-blur supports-[backdrop-filter]:bg-background/80',
                className
            )}
        >
            {leading}
            <AppLogo linked={!config.disableIndexPage} />
            <div className="flex min-w-0 flex-1 items-center gap-1">
                {children}
            </div>
            {config.displayServicesMenu && config.dashboardUrl ? (
                <Tooltip content={t('nav.dashboard', 'All services')}>
                    <Button variant="ghost" size="icon-sm" asChild>
                        <a
                            href={config.dashboardUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label={t('nav.dashboard', 'All services')}
                        >
                            <LayoutGridIcon />
                        </a>
                    </Button>
                </Tooltip>
            ) : null}
            <SettingsMenu />
            <UserMenu />
        </header>
    );
}

function SettingsMenu() {
    const {t, i18n} = useTranslation();

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
            <DropdownMenuContent align="end" className="w-56">
                <ThemeMenu />
                <DropdownMenuSub>
                    <DropdownMenuSubTrigger data-testid="language-menu">
                        <LanguagesIcon /> {t('settings.language', 'Language')}
                    </DropdownMenuSubTrigger>
                    <DropdownMenuSubContent>
                        <DropdownMenuRadioGroup
                            value={i18n.language}
                            onValueChange={lng => void i18n.changeLanguage(lng)}
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
    );
}

function UserMenu() {
    const {t} = useTranslation();
    const {user, isAuthenticated, status, login, redirecting, logout} =
        useAuth();

    if (status === 'loading') {
        return <span className="size-8" />;
    }

    if (!isAuthenticated || !user) {
        return (
            <Button
                size="sm"
                variant="outline"
                data-testid="sign-in"
                loading={redirecting}
                onClick={() => login()}
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
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <a
                        href={getAuthClient().getAccountUrl(
                            window.location.href
                        )}
                    >
                        <UserIcon /> {t('user.account', 'My account')}
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem data-testid="logout" onSelect={logout}>
                    <LogOutIcon /> {t('user.logout', 'Sign out')}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
