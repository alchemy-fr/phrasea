'use client';

import Link from 'next/link';
import {usePathname} from 'next/navigation';
import {useTranslation} from 'react-i18next';
import {BracesIcon, BugIcon, FileJsonIcon, UploadCloudIcon} from 'lucide-react';
import {DropdownMenuItem} from '@alchemy/next-ui/ui/menu';
import {
    AppLogo,
    LanguageMenu,
    ServicesMenuLink,
    SettingsMenu,
    UserMenu,
} from '@alchemy/next-ui/layout/menus';
import {useAuth} from '@/lib/auth/AuthProvider';
import {useConfig} from '@/lib/config/ConfigProvider';
import {getAuthClient} from '@/lib/auth/client';
import {languages} from '@/i18n';
import {routes} from '@/lib/routes';
import {cn} from '@alchemy/next-ui/utils/cn';
import {useModals} from '@alchemy/next-ui/modals/ModalProvider';
import {ThemeMenu} from '@alchemy/next-theme/ThemeMenu';
import {useUploaderUser} from '@/features/user/useUploaderUser';
import {UserDebugDialog} from '@/features/user/UserDebugDialog';
import {ManageGroupsMenuItem} from '@alchemy/next-ui/groups/ManageGroupsMenuItem';
import {groupsApi} from '@/lib/api/groups';

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
    const {t} = useTranslation();
    const {user, isAuthenticated, login, redirecting, logout} = useAuth();
    const config = useConfig();
    const pathname = usePathname();
    const {data: me} = useUploaderUser();
    const {openModal} = useModals();

    return (
        <header
            data-testid="topbar"
            className="flex h-12 shrink-0 items-center gap-2 bg-background px-2"
        >
            <AppLogo
                href={routes.home()}
                data-testid="home-link"
                name="Uploader"
                icon={<UploadCloudIcon />}
                logo={config.logo}
            />

            <nav
                aria-label={t('nav.admin', 'Administration')}
                className="ml-2 flex min-w-0 flex-1 items-center gap-1"
            >
                {me?.permissions.form_schema ? (
                    <NavLink
                        href={routes.forms()}
                        testId="nav-forms"
                        active={pathname.startsWith(routes.forms())}
                        icon={<FileJsonIcon />}
                        label={t('nav.form_editor', 'Form Editor')}
                    />
                ) : null}
                {me?.permissions.target_data ? (
                    <NavLink
                        href={routes.targetDataList()}
                        testId="nav-target-data"
                        active={pathname.startsWith(routes.targetDataList())}
                        icon={<BracesIcon />}
                        label={t(
                            'nav.target_data_editor',
                            'Target Data Editor'
                        )}
                    />
                ) : null}
            </nav>

            {config.displayServicesMenu && config.dashboardUrl ? (
                <ServicesMenuLink url={config.dashboardUrl} newTab={false} />
            ) : null}

            <SettingsMenu>
                <ThemeMenu custom={config.theme} />
                <LanguageMenu languages={languages.supportedLanguages} />
            </SettingsMenu>

            <UserMenu
                user={isAuthenticated ? user : undefined}
                redirecting={redirecting}
                accountUrl={() =>
                    getAuthClient().getAccountUrl(window.location.href)
                }
                onLogin={() => login()}
                onLogout={logout}
            >
                {user?.canManageGroups ? (
                    <ManageGroupsMenuItem api={groupsApi} />
                ) : null}
                {config.devMode ? (
                    <DropdownMenuItem
                        data-testid="user-debug"
                        onSelect={() => openModal(UserDebugDialog, {})}
                    >
                        <BugIcon /> {t('user.debug', 'Debug user')}
                    </DropdownMenuItem>
                ) : null}
            </UserMenu>
        </header>
    );
}
