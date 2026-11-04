'use client';

import Link from 'next/link';
import {usePathname} from 'next/navigation';
import {useTranslation} from 'react-i18next';
import {
    BracesIcon,
    BugIcon,
    FileJsonIcon,
    LanguagesIcon,
    LayoutGridIcon,
    LogInIcon,
    LogOutIcon,
    SettingsIcon,
    UploadCloudIcon,
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
import {useConfig} from '@/lib/config/ConfigProvider';
import {getAuthClient} from '@/lib/auth/client';
import {supportedLanguages} from '@/i18n';
import {routes} from '@/lib/routes';
import {cn} from '@/lib/utils/cn';
import {useModals} from '@/components/modals/ModalProvider';
import {ThemeMenu} from '@/features/theme/ThemeMenu';
import {useUploaderUser} from '@/features/user/useUploaderUser';
import {UserDebugDialog} from '@/features/user/UserDebugDialog';

function NavLink({
    href,
    active,
    icon,
    label,
    testId,
}: {
    href: string;
    active: boolean;
    icon: React.ReactNode;
    label: string;
    testId: string;
}) {
    return (
        <Link
            href={href}
            data-testid={testId}
            aria-current={active ? 'page' : undefined}
            className={cn(
                'inline-flex h-8 shrink-0 items-center gap-1.5 rounded-md px-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground [&_svg]:size-4',
                active && 'bg-accent text-foreground'
            )}
        >
            {icon}
            <span className="hidden md:inline">{label}</span>
        </Link>
    );
}

/**
 * The application header: the logo (back to the targets), the admin editors
 * the user may open, then the services, settings and user menus.
 */
export function TopBar() {
    const {t, i18n} = useTranslation();
    const {user, isAuthenticated, login, redirecting, logout} = useAuth();
    const config = useConfig();
    const pathname = usePathname();
    const {data: me} = useUploaderUser();
    const {openModal} = useModals();

    return (
        <header
            data-testid="topbar"
            className="flex h-12 shrink-0 items-center gap-2 border-b bg-background px-2"
        >
            <Link
                href={routes.home()}
                data-testid="home-link"
                className="flex items-center gap-2 rounded-md px-2 py-1 font-semibold hover:bg-accent"
            >
                {config.logo?.src ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img
                        src={config.logo.src}
                        alt="Uploader"
                        className="h-6"
                        style={parseInlineStyle(config.logo.style)}
                    />
                ) : (
                    <span className="flex size-7 items-center justify-center rounded-md bg-primary text-primary-foreground">
                        <UploadCloudIcon className="size-4" />
                    </span>
                )}
                <span className="hidden sm:inline">Uploader</span>
            </Link>

            <nav
                aria-label={t('nav.admin', 'Administration')}
                className="ml-2 flex min-w-0 flex-1 items-center gap-1"
            >
                {me?.permissions.form_schema ? (
                    <NavLink
                        href={routes.formSchemas()}
                        testId="nav-form-schemas"
                        active={pathname.startsWith(routes.formSchemas())}
                        icon={<FileJsonIcon />}
                        label={t('nav.form_editor', 'Form Editor')}
                    />
                ) : null}
                {me?.permissions.target_data ? (
                    <NavLink
                        href={routes.targetParams()}
                        testId="nav-target-params"
                        active={pathname.startsWith(routes.targetParams())}
                        icon={<BracesIcon />}
                        label={t(
                            'nav.target_data_editor',
                            'Target Data Editor'
                        )}
                    />
                ) : null}
            </nav>

            {config.displayServicesMenu && config.dashboardUrl ? (
                <Tooltip content={t('nav.dashboard', 'All services')}>
                    <Button variant="ghost" size="icon-sm" asChild>
                        <a
                            href={config.dashboardUrl}
                            data-testid="services-link"
                            aria-label={t('nav.dashboard', 'All services')}
                        >
                            <LayoutGridIcon />
                        </a>
                    </Button>
                </Tooltip>
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
                        {config.devMode ? (
                            <DropdownMenuItem
                                data-testid="user-debug"
                                onSelect={() => openModal(UserDebugDialog, {})}
                            >
                                <BugIcon /> {t('user.debug', 'Debug user')}
                            </DropdownMenuItem>
                        ) : null}
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

/** `margin: 4px; height: 20px` => React style object */
function parseInlineStyle(
    style: string | undefined
): React.CSSProperties | undefined {
    if (!style) {
        return undefined;
    }
    const result: Record<string, string> = {};
    for (const declaration of style.split(';')) {
        const [prop, ...rest] = declaration.split(':');
        const value = rest.join(':').trim();
        if (prop?.trim() && value) {
            result[
                prop
                    .trim()
                    .replace(/-([a-z])/g, (_, c: string) => c.toUpperCase())
            ] = value;
        }
    }

    return result;
}
