'use client';

import {Fragment} from 'react';
import Link from 'next/link';
import {usePathname, useRouter} from 'next/navigation';
import {useTranslation} from 'react-i18next';
import {
    BellIcon,
    ChevronRightIcon,
    ImagesIcon,
    LayoutGridIcon,
    LogInIcon,
    LogOutIcon,
    PanelLeftIcon,
    SettingsIcon,
    UserIcon,
    LanguagesIcon,
    ListChecksIcon,
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
import {AppRole, useAuth} from '@/lib/auth/AuthProvider';
import {useConfig} from '@/lib/config/ConfigProvider';
import {useLayoutStore} from './layoutStore';
import {topBarHeight} from './chrome';
import {supportedLanguages} from '@/i18n';
import {routes} from '@/lib/routes';
import {useModals} from '@/components/modals/ModalProvider';
import {DataLocaleDialog} from '@/features/preferences/DataLocaleDialog';
import {DisplayProfileMenuItem} from '@/features/profiles/DisplayProfileMenuItem';
import {ThemeMenu} from '@/features/theme/ThemeMenu';
import {NotificationsMenu} from '@/features/notifications/NotificationsMenu';
import {getAuthClient} from '@/lib/auth/client';
import {cn} from '@/lib/utils/cn';

export function TopBar() {
    const {t, i18n} = useTranslation();
    const {user, isAuthenticated, login, logout, hasRole} = useAuth();
    const config = useConfig();
    const router = useRouter();
    const pathname = usePathname();
    const toggleLeftPanel = useLayoutStore(s => s.toggleLeftPanel);
    const pageTrail = useLayoutStore(s => s.pageTrail);
    const {openModal} = useModals();
    const isAdmin = hasRole(AppRole.DataboxAdmin) || hasRole(AppRole.Admin);

    const changeLanguage = (lng: string) => {
        void i18n.changeLanguage(lng);
        router.refresh();
    };

    return (
        <header
            data-testid="topbar"
            className={cn(
                'flex shrink-0 items-center gap-2 border-b bg-background px-2',
                topBarHeight
            )}
        >
            <Tooltip content={t('layout.toggle_panel', 'Toggle side panel')}>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    data-testid="toggle-left-panel"
                    onClick={() => toggleLeftPanel()}
                >
                    <PanelLeftIcon />
                </Button>
            </Tooltip>
            <Link
                href={routes.assets()}
                className="flex items-center gap-2 rounded-md px-2 py-1 font-semibold hover:bg-accent"
                onClick={e => {
                    if (pathname === routes.assets()) {
                        e.preventDefault();
                        router.push(routes.assets());
                    }
                }}
            >
                {config.logo?.src ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img src={config.logo.src} alt="Databox" className="h-6" />
                ) : (
                    <span className="flex size-7 items-center justify-center rounded-md bg-primary text-primary-foreground">
                        <ImagesIcon className="size-4" />
                    </span>
                )}
                <span className="hidden sm:inline">Databox</span>
            </Link>

            {/* A screen opened over the main view takes over this row: where
                the user is, and the way back */}
            {pageTrail.length > 0 ? (
                <nav
                    data-testid="page-trail"
                    aria-label={t('nav.breadcrumb', 'Breadcrumb')}
                    className="ml-1 flex min-w-0 flex-1 items-center gap-1 text-sm"
                >
                    <Link
                        href={routes.assets()}
                        data-testid="trail-root"
                        className="inline-flex h-8 shrink-0 items-center gap-1.5 rounded-md px-2 font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground [&_svg]:size-4"
                    >
                        <LayoutGridIcon /> {t('nav.assets', 'Assets')}
                    </Link>
                    {pageTrail.map((entry, i) => {
                        const last = i === pageTrail.length - 1;

                        return (
                            <Fragment key={entry.id}>
                                <ChevronRightIcon className="size-4 shrink-0 text-muted-foreground" />
                                {last ? (
                                    <span
                                        data-testid="page-title"
                                        title={entry.label}
                                        className="min-w-0 truncate px-1 font-semibold"
                                    >
                                        {entry.label}
                                    </span>
                                ) : entry.href ? (
                                    <Link
                                        href={entry.href}
                                        title={entry.label}
                                        className="max-w-[20ch] truncate rounded-md px-2 py-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                                    >
                                        {entry.label}
                                    </Link>
                                ) : (
                                    <span className="max-w-[20ch] truncate px-1 text-muted-foreground">
                                        {entry.label}
                                    </span>
                                )}
                            </Fragment>
                        );
                    })}
                </nav>
            ) : (
                <div className="flex-1" />
            )}

            {config.displayServicesMenu && config.dashboardUrl ? (
                <Tooltip content={t('nav.dashboard', 'All services')}>
                    <Button variant="ghost" size="icon-sm" asChild>
                        <a
                            href={config.dashboardUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <LayoutGridIcon />
                        </a>
                    </Button>
                </Tooltip>
            ) : null}

            {isAuthenticated && config.notifications ? (
                <NotificationsMenu />
            ) : null}

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
                <DropdownMenuContent align="end" className="w-60">
                    {isAuthenticated ? <DisplayProfileMenuItem /> : null}
                    <ThemeMenu />
                    <DropdownMenuSub>
                        <DropdownMenuSubTrigger>
                            <LanguagesIcon />{' '}
                            {t('settings.language', 'Language')}
                        </DropdownMenuSubTrigger>
                        <DropdownMenuSubContent>
                            <DropdownMenuRadioGroup
                                value={i18n.language}
                                onValueChange={changeLanguage}
                            >
                                {supportedLanguages.map(l => (
                                    <DropdownMenuRadioItem
                                        key={l.code}
                                        value={l.code}
                                    >
                                        <span className={`fi fi-${l.flag}`} />{' '}
                                        {l.label}
                                    </DropdownMenuRadioItem>
                                ))}
                            </DropdownMenuRadioGroup>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                onSelect={() => openModal(DataLocaleDialog, {})}
                            >
                                {t('settings.data_locale', 'Data language…')}
                            </DropdownMenuItem>
                        </DropdownMenuSubContent>
                    </DropdownMenuSub>
                    {isAdmin ? (
                        <>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                onSelect={() =>
                                    router.push(routes.operationTasks())
                                }
                            >
                                <ListChecksIcon />{' '}
                                {t(
                                    'settings.operation_tasks',
                                    'Operation tasks'
                                )}
                            </DropdownMenuItem>
                        </>
                    ) : null}
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
                        <DropdownMenuItem onSelect={logout}>
                            <LogOutIcon /> {t('user.logout', 'Sign out')}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            ) : (
                <Button size="sm" data-testid="sign-in" onClick={() => login()}>
                    <LogInIcon /> {t('user.login', 'Sign in')}
                </Button>
            )}
        </header>
    );
}

export {BellIcon};
