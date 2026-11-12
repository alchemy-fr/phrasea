'use client';

import {useMemo} from 'react';
import {useTranslation} from 'react-i18next';
import {LayoutGridIcon} from 'lucide-react';
import {
    AppLogo,
    LanguageMenu,
    SettingsMenu,
    UserMenu,
} from '@alchemy/next-ui/layout/menus';
import {ThemeMenu} from '@alchemy/next-theme/ThemeMenu';
import {useAuth} from '@/lib/auth/AuthProvider';
import {useConfig} from '@/lib/config/ConfigProvider';
import {getAuthClient} from '@/lib/auth/client';
import {languages} from '@/i18n';
import {routes} from '@/lib/routes';
import {createDataboxGroupsApi} from '@/lib/api/groups';
import {ManageGroupsMenuItem} from '@alchemy/next-ui/groups/ManageGroupsMenuItem';

/**
 * The application header: the logo, then the settings and user menus. Not
 * displayed in the services menu of the other clients, whatever the width.
 */
export function TopBar() {
    const {t} = useTranslation();
    const {user, isAuthenticated, login, redirecting, logout} = useAuth();
    const config = useConfig();
    // The groups are managed through the Databox API: not without Databox
    const databoxApiUrl = config.apps.databox?.apiUrl;
    const groupsApi = useMemo(
        () =>
            databoxApiUrl ? createDataboxGroupsApi(databoxApiUrl) : undefined,
        [databoxApiUrl]
    );

    return (
        <header
            data-testid="topbar"
            className="flex h-12 shrink-0 items-center gap-2 border-b bg-background px-2 embedded:hidden"
        >
            <AppLogo
                href={routes.home()}
                data-testid="home-link"
                name={t('app.title', 'Dashboard')}
                nameClassName="truncate"
                icon={<LayoutGridIcon />}
                logo={config.logo}
            />

            <div className="flex-1" />

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
                {groupsApi && user?.canManageGroups ? (
                    <ManageGroupsMenuItem api={groupsApi} />
                ) : null}
            </UserMenu>
        </header>
    );
}
