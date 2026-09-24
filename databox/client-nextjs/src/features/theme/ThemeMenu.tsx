'use client';

import {useGuardedRouter} from '@/components/modals/UnsavedChangesGuard';
import {useTranslation} from 'react-i18next';
import {useTheme} from 'next-themes';
import {
    BrushIcon,
    MonitorIcon,
    MoonIcon,
    PaletteIcon,
    SunIcon,
} from 'lucide-react';
import {
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
} from '@/components/ui/menu';
import {AppRole, useAuth} from '@/lib/auth/AuthProvider';
import {useConfig} from '@/lib/config/ConfigProvider';
import {usePreferencesStore} from '@/features/preferences/store';
import {routes} from '@/lib/routes';
import {baseThemeColors} from './customTheme';
import {CUSTOM_THEME_ID, DEFAULT_THEME_ID, themePresets} from './presets';
import {useThemeStore} from './themeStore';

export function ThemeSwatch({
    background,
    primary,
    className,
}: {
    background: string;
    primary: string;
    className?: string;
}) {
    return (
        <span
            aria-hidden
            data-slot="theme-swatch"
            className={
                className ??
                'size-4 shrink-0 rounded-full border border-foreground/25'
            }
            style={{
                background: `linear-gradient(135deg, ${background} 50%, ${primary} 50%)`,
            }}
        />
    );
}

/**
 * The "Theme" sub-menu of the settings menu: the general light / dark /
 * system appearance, then the theme (base palette, presets, the organisation
 * theme when one is defined) and, for administrators, the link to its editor.
 */
export function ThemeMenu() {
    const {t} = useTranslation();
    const router = useGuardedRouter();
    const {hasRole} = useAuth();
    const {theme: appearance, setTheme: setAppearance} = useTheme();
    const updatePreference = usePreferencesStore(s => s.updatePreference);
    const custom = useConfig().theme;
    const theme = useThemeStore(s => s.theme);
    const setTheme = useThemeStore(s => s.setTheme);
    const isAdmin = hasRole(AppRole.DataboxAdmin) || hasRole(AppRole.Admin);

    const changeAppearance = (value: string) => {
        setAppearance(value);
        void updatePreference('theme', value);
    };

    return (
        <DropdownMenuSub>
            <DropdownMenuSubTrigger data-testid="theme-menu">
                <PaletteIcon /> {t('settings.theme', 'Theme')}
            </DropdownMenuSubTrigger>
            <DropdownMenuSubContent className="max-h-[80vh] overflow-y-auto">
                <DropdownMenuLabel>
                    {t('theme.appearance_label', 'Appearance')}
                </DropdownMenuLabel>
                <DropdownMenuRadioGroup
                    value={appearance}
                    onValueChange={changeAppearance}
                >
                    <DropdownMenuRadioItem
                        value="light"
                        data-testid="appearance-light"
                    >
                        <SunIcon /> {t('settings.theme_light', 'Light')}
                    </DropdownMenuRadioItem>
                    <DropdownMenuRadioItem
                        value="dark"
                        data-testid="appearance-dark"
                    >
                        <MoonIcon /> {t('settings.theme_dark', 'Dark')}
                    </DropdownMenuRadioItem>
                    <DropdownMenuRadioItem
                        value="system"
                        data-testid="appearance-system"
                    >
                        <MonitorIcon /> {t('settings.theme_system', 'System')}
                    </DropdownMenuRadioItem>
                </DropdownMenuRadioGroup>
                <DropdownMenuSeparator />
                <DropdownMenuLabel>
                    {t('theme.presets_label', 'Theme')}
                </DropdownMenuLabel>
                <DropdownMenuRadioGroup
                    value={
                        theme ??
                        (custom?.default ? CUSTOM_THEME_ID : DEFAULT_THEME_ID)
                    }
                    onValueChange={setTheme}
                >
                    <DropdownMenuRadioItem
                        value={DEFAULT_THEME_ID}
                        data-testid="theme-default"
                    >
                        <ThemeSwatch
                            background={baseThemeColors.light.background}
                            primary={baseThemeColors.light.primary}
                        />{' '}
                        {t('theme.preset.default', 'Default')}
                    </DropdownMenuRadioItem>
                    {themePresets.map(preset => (
                        <DropdownMenuRadioItem
                            key={preset.id}
                            value={preset.id}
                            data-testid={`theme-${preset.id}`}
                        >
                            <ThemeSwatch
                                background={preset.swatch[0]}
                                primary={preset.swatch[1]}
                            />{' '}
                            {t(preset.label[0], preset.label[1])}
                        </DropdownMenuRadioItem>
                    ))}
                    {custom ? (
                        <>
                            <DropdownMenuSeparator />
                            <DropdownMenuLabel>
                                {t('theme.organisation_label', 'Organisation')}
                            </DropdownMenuLabel>
                            <DropdownMenuRadioItem
                                value={CUSTOM_THEME_ID}
                                data-testid="theme-custom"
                            >
                                <ThemeSwatch
                                    background={custom.swatch[0]}
                                    primary={custom.swatch[1]}
                                />{' '}
                                <span className="truncate">{custom.name}</span>
                            </DropdownMenuRadioItem>
                        </>
                    ) : null}
                </DropdownMenuRadioGroup>
                {isAdmin ? (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            data-testid="theme-customize"
                            onSelect={() => router.push(routes.themeSettings())}
                        >
                            <BrushIcon />{' '}
                            {t('theme.customize', 'Customize theme…')}
                        </DropdownMenuItem>
                    </>
                ) : null}
            </DropdownMenuSubContent>
        </DropdownMenuSub>
    );
}
