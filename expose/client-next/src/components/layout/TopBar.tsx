'use client';

import {type ReactNode} from 'react';
import {LayoutGridIcon} from 'lucide-react';
import {
    AppLogo as BaseAppLogo,
    LanguageMenu,
    ServicesMenuLink,
    SettingsMenu,
    UserMenu,
} from '@alchemy/next-ui/layout/menus';
import {ThemeMenu} from '@alchemy/next-theme/ThemeMenu';
import {useAuth} from '@/lib/auth/AuthProvider';
import {getAuthClient} from '@/lib/auth/client';
import {useConfig} from '@/lib/config/ConfigProvider';
import {languages} from '@/i18n';
import {routes} from '@/lib/routes';
import {cn} from '@alchemy/next-ui/utils/cn';

export function AppLogo({linked = true}: {linked?: boolean}) {
    const config = useConfig();

    return (
        <BaseAppLogo
            name="Expose"
            icon={<LayoutGridIcon />}
            logo={config.logo}
            href={linked ? routes.home() : undefined}
            data-testid="app-logo"
        />
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
    const config = useConfig();
    const {user, isAuthenticated, status, login, redirecting, logout} =
        useAuth();

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
                <ServicesMenuLink url={config.dashboardUrl} />
            ) : null}
            <SettingsMenu>
                <ThemeMenu custom={config.theme} />
                <LanguageMenu languages={languages.supportedLanguages} />
            </SettingsMenu>
            <UserMenu
                user={isAuthenticated ? user : undefined}
                loading={status === 'loading'}
                redirecting={redirecting}
                signInVariant="outline"
                accountUrl={() =>
                    getAuthClient().getAccountUrl(window.location.href)
                }
                onLogin={() => login()}
                onLogout={logout}
            />
        </header>
    );
}
