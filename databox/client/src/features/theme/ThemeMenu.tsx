'use client';

import {useTranslation} from 'react-i18next';
import {BrushIcon} from 'lucide-react';
import {ThemeMenu as BaseThemeMenu} from '@alchemy/next-theme/ThemeMenu';
import {
    DropdownMenuItem,
    DropdownMenuSeparator,
} from '@alchemy/next-ui/ui/menu';
import {useGuardedRouter} from '@alchemy/next-ui/modals/UnsavedChangesGuard';
import {AppRole, useAuth} from '@/lib/auth/AuthProvider';
import {useConfig} from '@/lib/config/ConfigProvider';
import {usePreferencesStore} from '@/features/preferences/store';
import {routes} from '@/lib/routes';

/**
 * The theme sub-menu, whose choices are saved in the user preferences, with
 * the link to the editor of the organisation theme for administrators.
 */
export function ThemeMenu() {
    const {t} = useTranslation();
    const router = useGuardedRouter();
    const {hasRole} = useAuth();
    const updatePreference = usePreferencesStore(s => s.updatePreference);
    const isAdmin = hasRole(AppRole.DataboxAdmin) || hasRole(AppRole.Admin);

    return (
        <BaseThemeMenu
            custom={useConfig().theme}
            onAppearanceChange={value => void updatePreference('theme', value)}
            onThemeChange={value => void updatePreference('palette', value)}
        >
            {isAdmin ? (
                <>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        data-testid="theme-customize"
                        onSelect={() => router.push(routes.themeSettings())}
                    >
                        <BrushIcon /> {t('theme.customize', 'Customize theme…')}
                    </DropdownMenuItem>
                </>
            ) : null}
        </BaseThemeMenu>
    );
}
