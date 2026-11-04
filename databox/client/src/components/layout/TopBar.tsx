'use client';

import {Fragment, ReactNode} from 'react';
import Link from 'next/link';
import {usePathname} from 'next/navigation';
import {useGuardedRouter} from '@alchemy/next-ui/modals/UnsavedChangesGuard';
import {useTranslation} from 'react-i18next';
import {
    BellIcon,
    ChevronRightIcon,
    ImagesIcon,
    LayoutGridIcon,
    PanelLeftIcon,
    ShieldIcon,
    ListChecksIcon,
    PlugIcon,
    UserRoundCogIcon,
    UndoIcon,
    WorkflowIcon,
} from 'lucide-react';
import {Button} from '@alchemy/next-ui/ui/button';
import {
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuShortcut,
} from '@alchemy/next-ui/ui/menu';
import {Tooltip} from '@alchemy/next-ui/ui/overlays';
import {
    AppLogo,
    LanguageMenu,
    ServicesMenuLink,
    SettingsMenu,
    UserMenu,
} from '@alchemy/next-ui/layout/menus';
import {AppRole, useAuth} from '@/lib/auth/AuthProvider';
import {useConfig} from '@/lib/config/ConfigProvider';
import {useLayoutStore} from './layoutStore';
import {topBarHeight} from './chrome';
import {languages} from '@/i18n';
import {routes} from '@/lib/routes';
import {useModals} from '@alchemy/next-ui/modals/ModalProvider';
import {DataLocaleDialog} from '@/features/preferences/DataLocaleDialog';
import {InstancePermissionsDialog} from '@/features/permissions/InstancePermissionsDialog';
import {
    SWITCH_USER_SHORTCUT,
    useSwitchUserShortcut,
} from '@/features/impersonation/useSwitchUserShortcut';
import {ProfileMenu} from '@/features/profiles/ProfileMenu';
import {ThemeMenu} from '@/features/theme/ThemeMenu';
import {NotificationsMenu} from '@/features/notifications/NotificationsMenu';
import {getAuthClient} from '@/lib/auth/client';
import {useOptionalSearch} from '@/features/search/SearchProvider';
import {cn} from '@alchemy/next-ui/utils/cn';

/**
 * The application header: the logo, where the user is and the way back, then
 * the notifications, settings and user menus.
 *
 * The `public` variant heads the pages living outside of the application
 * shell (public share): no side panel to toggle nor app screen trail, the
 * `children` taking the middle of the row instead.
 */
export function TopBar({
    variant = 'app',
    children,
}: {
    variant?: 'app' | 'public';
    children?: ReactNode;
}) {
    const {t} = useTranslation();
    const isPublic = variant === 'public';
    const {
        user,
        realUser,
        impersonating,
        canImpersonate,
        stopImpersonating,
        isAuthenticated,
        login,
        redirecting,
        logout,
        hasRole,
    } = useAuth();
    const config = useConfig();
    const router = useGuardedRouter();
    const pathname = usePathname();
    const toggleLeftPanel = useLayoutStore(s => s.toggleLeftPanel);
    const pageTrail = useLayoutStore(s => s.pageTrail);
    // The way back from a screen stacked over the search: that search, as the
    // user left it
    const searchUrl = useOptionalSearch()?.url ?? routes.assets();
    const {openModal} = useModals();
    const openSwitchUser = useSwitchUserShortcut();
    const isAdmin = hasRole(AppRole.DataboxAdmin) || hasRole(AppRole.Admin);

    return (
        <header
            data-testid="topbar"
            className={cn(
                'flex shrink-0 items-center gap-2 border-b bg-background px-2',
                topBarHeight
            )}
        >
            {isPublic ? null : (
                <Tooltip
                    content={t('layout.toggle_panel', 'Toggle side panel')}
                >
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        data-testid="toggle-left-panel"
                        onClick={() => toggleLeftPanel()}
                    >
                        <PanelLeftIcon />
                    </Button>
                </Tooltip>
            )}
            <AppLogo
                href={routes.assets()}
                name="Databox"
                icon={<ImagesIcon />}
                logo={config.logo}
                onClick={e => {
                    if (pathname === routes.assets()) {
                        e.preventDefault();
                        router.push(routes.assets());
                    }
                }}
            />

            {/* A screen opened over the main view takes over this row: where
                the user is, and the way back */}
            {isPublic ? (
                <div className="ml-1 flex min-w-0 flex-1 items-center gap-2">
                    {children}
                </div>
            ) : pageTrail.length > 0 ? (
                <nav
                    data-testid="page-trail"
                    aria-label={t('nav.breadcrumb', 'Breadcrumb')}
                    className="ml-1 flex min-w-0 flex-1 items-center gap-1 text-sm"
                >
                    <Link
                        href={searchUrl}
                        scroll={false}
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
                <ServicesMenuLink url={config.dashboardUrl} />
            ) : null}

            {isAuthenticated && config.notifications ? (
                <NotificationsMenu />
            ) : null}

            <SettingsMenu className="w-60">
                {isAuthenticated && !isPublic ? <ProfileMenu /> : null}
                <ThemeMenu />
                <LanguageMenu
                    languages={languages.supportedLanguages}
                    onChange={() => router.refresh()}
                >
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        onSelect={() => openModal(DataLocaleDialog, {})}
                    >
                        {t('settings.data_locale', 'Data language…')}
                    </DropdownMenuItem>
                </LanguageMenu>
                {isAdmin ? (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onSelect={() =>
                                router.push(routes.operationTasks())
                            }
                        >
                            <ListChecksIcon />{' '}
                            {t('settings.operation_tasks', 'Operation tasks')}
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            data-testid="settings-workflows"
                            onSelect={() => router.push(routes.workflows())}
                        >
                            <WorkflowIcon />{' '}
                            {t('settings.workflows', 'Workflows')}
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            onSelect={() =>
                                router.push(routes.instanceIntegrations())
                            }
                        >
                            <PlugIcon />{' '}
                            {t(
                                'settings.instance_integrations',
                                'Instance integrations'
                            )}
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            data-testid="settings-instance-permissions"
                            onSelect={() =>
                                openModal(InstancePermissionsDialog, {})
                            }
                        >
                            <ShieldIcon />{' '}
                            {t(
                                'settings.instance_permissions',
                                'Instance permissions'
                            )}
                        </DropdownMenuItem>
                    </>
                ) : null}
            </SettingsMenu>

            <UserMenu
                user={isAuthenticated ? user : undefined}
                redirecting={redirecting}
                accountUrl={() =>
                    getAuthClient().getAccountUrl(window.location.href)
                }
                onLogin={() => login()}
                onLogout={logout}
                details={
                    impersonating && realUser ? (
                        <div
                            data-testid="impersonated-by"
                            className="truncate text-xs font-normal text-muted-foreground"
                        >
                            {t('impersonation.via', 'via {{name}}', {
                                name: realUser.name ?? realUser.username,
                            })}
                        </div>
                    ) : null
                }
                leadingItems={
                    <>
                        {openSwitchUser ? (
                            <DropdownMenuItem
                                data-testid="switch-user"
                                onSelect={openSwitchUser}
                            >
                                <UserRoundCogIcon />{' '}
                                {t('impersonation.switch', 'Switch user…')}
                                <DropdownMenuShortcut>
                                    {SWITCH_USER_SHORTCUT}
                                </DropdownMenuShortcut>
                            </DropdownMenuItem>
                        ) : null}
                        {impersonating && realUser ? (
                            <DropdownMenuItem
                                data-testid="stop-impersonating"
                                onSelect={stopImpersonating}
                            >
                                <UndoIcon />{' '}
                                {t('impersonation.back', 'Back to {{name}}', {
                                    name: realUser.name ?? realUser.username,
                                })}
                            </DropdownMenuItem>
                        ) : null}
                        {canImpersonate ? <DropdownMenuSeparator /> : null}
                    </>
                }
            />
        </header>
    );
}

export {BellIcon};
