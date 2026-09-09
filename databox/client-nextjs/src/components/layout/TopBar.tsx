'use client';

import Link from 'next/link';
import {usePathname, useRouter} from 'next/navigation';
import {useTranslation} from 'react-i18next';
import {
    BellIcon,
    FileTextIcon,
    ImagesIcon,
    LayoutGridIcon,
    LogInIcon,
    LogOutIcon,
    MoonIcon,
    PanelLeftIcon,
    SettingsIcon,
    SunIcon,
    MonitorIcon,
    UserIcon,
    LanguagesIcon,
    PaletteIcon,
    ListChecksIcon,
} from 'lucide-react';
import {useTheme} from 'next-themes';
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
import {Avatar, Badge} from '@/components/ui/misc';
import {AppRole, useAuth} from '@/lib/auth/AuthProvider';
import {useConfig} from '@/lib/config/ConfigProvider';
import {useLayoutStore} from './layoutStore';
import {supportedLanguages} from '@/i18n';
import {usePreferencesStore} from '@/features/preferences/store';
import {routes} from '@/lib/routes';
import {useModals} from '@/components/modals/ModalProvider';
import {DataLocaleDialog} from '@/features/preferences/DataLocaleDialog';
import {DisplayProfileMenuItem} from '@/features/profiles/DisplayProfileMenuItem';
import {NotificationsMenu} from '@/features/notifications/NotificationsMenu';
import {getAuthClient} from '@/lib/auth/client';
import {cn} from '@/lib/utils/cn';

export function TopBar() {
    const {t, i18n} = useTranslation();
    const {user, isAuthenticated, login, logout, hasRole} = useAuth();
    const config = useConfig();
    const router = useRouter();
    const pathname = usePathname();
    const {theme, setTheme} = useTheme();
    const toggleLeftPanel = useLayoutStore(s => s.toggleLeftPanel);
    const updatePreference = usePreferencesStore(s => s.updatePreference);
    const {openModal} = useModals();
    const isAdmin = hasRole(AppRole.DataboxAdmin) || hasRole(AppRole.Admin);

    const changeTheme = (value: string) => {
        setTheme(value);
        void updatePreference('theme', value);
    };

    const changeLanguage = (lng: string) => {
        void i18n.changeLanguage(lng);
        router.refresh();
    };

    return (
        <header className="flex h-12 shrink-0 items-center gap-2 border-b bg-background px-2">
            <Tooltip content={t('layout.toggle_panel', 'Toggle side panel')}>
                <Button
                    variant="ghost"
                    size="icon-sm"
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

            {isAdmin ? (
                <nav className="ml-2 hidden items-center gap-1 md:flex">
                    <NavLink
                        href={routes.assets()}
                        active={pathname.startsWith('/assets')}
                    >
                        <LayoutGridIcon /> {t('nav.assets', 'Assets')}
                    </NavLink>
                    <NavLink
                        href={routes.pages()}
                        active={pathname.startsWith('/pages')}
                    >
                        <FileTextIcon /> {t('nav.pages', 'Pages')}
                        <Badge
                            variant="warning"
                            className="ml-1 px-1 py-0 text-[10px]"
                        >
                            BETA
                        </Badge>
                    </NavLink>
                </nav>
            ) : null}

            <div className="flex-1" />

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
                        aria-label={t('nav.settings', 'Settings')}
                    >
                        <SettingsIcon />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-60">
                    {isAuthenticated ? <DisplayProfileMenuItem /> : null}
                    <DropdownMenuSub>
                        <DropdownMenuSubTrigger>
                            <PaletteIcon /> {t('settings.theme', 'Theme')}
                        </DropdownMenuSubTrigger>
                        <DropdownMenuSubContent>
                            <DropdownMenuRadioGroup
                                value={theme}
                                onValueChange={changeTheme}
                            >
                                <DropdownMenuRadioItem value="light">
                                    <SunIcon />{' '}
                                    {t('settings.theme_light', 'Light')}
                                </DropdownMenuRadioItem>
                                <DropdownMenuRadioItem value="dark">
                                    <MoonIcon />{' '}
                                    {t('settings.theme_dark', 'Dark')}
                                </DropdownMenuRadioItem>
                                <DropdownMenuRadioItem value="system">
                                    <MonitorIcon />{' '}
                                    {t('settings.theme_system', 'System')}
                                </DropdownMenuRadioItem>
                            </DropdownMenuRadioGroup>
                        </DropdownMenuSubContent>
                    </DropdownMenuSub>
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
                <Button size="sm" onClick={() => login()}>
                    <LogInIcon /> {t('user.login', 'Sign in')}
                </Button>
            )}
        </header>
    );
}

function NavLink({
    href,
    active,
    children,
}: {
    href: string;
    active: boolean;
    children: React.ReactNode;
}) {
    return (
        <Link
            href={href}
            className={cn(
                'inline-flex h-8 items-center gap-1.5 rounded-md px-2.5 text-sm font-medium transition-colors hover:bg-accent [&_svg]:size-4',
                active
                    ? 'bg-accent text-accent-foreground'
                    : 'text-muted-foreground'
            )}
        >
            {children}
        </Link>
    );
}

export {BellIcon};
