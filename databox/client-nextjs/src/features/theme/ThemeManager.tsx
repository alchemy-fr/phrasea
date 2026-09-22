'use client';

import {useEffect, useMemo} from 'react';
import {useTheme} from 'next-themes';
import {useConfig} from '@/lib/config/ConfigProvider';
import {applyThemeVars, themeFontFacesCss, themeToCssVars} from './customTheme';
import {
    CUSTOM_THEME_ID,
    DEFAULT_THEME_ID,
    isKnownThemeId,
    themePresets,
} from './presets';
import {useThemeStore} from './themeStore';
import {buildPrepaintScript, prepaintOptionsFor} from './themeScript';

const presetIds = themePresets.map(p => p.id);

const previewFontsId = 'client-theme-preview-fonts';

/**
 * Applies the selected theme next to next-themes (which handles the light /
 * dark / system appearance with the `dark` class): sets `data-theme` on
 * <html>, applies the draft previewed by the theme editor as inline custom
 * properties for the current appearance, and renders the pre-paint script so
 * that reloads do not flash the base palette.
 */
export function ThemeManager() {
    const meta = useConfig().theme;
    const {resolvedTheme} = useTheme();
    const theme = useThemeStore(s => s.theme);
    const setTheme = useThemeStore(s => s.setTheme);
    const preview = useThemeStore(s => s.preview);
    const script = useMemo(
        () => buildPrepaintScript(prepaintOptionsFor(meta, presetIds)),
        [meta]
    );

    // The effective theme: the stored choice, else the organisation default
    const effective =
        theme ?? (meta?.default ? CUSTOM_THEME_ID : DEFAULT_THEME_ID);

    useEffect(() => {
        if (!isKnownThemeId(effective, !!meta)) {
            // The organisation theme was removed, or a stale value
            setTheme(DEFAULT_THEME_ID, {persist: false});
        }
    }, [effective, meta, setTheme]);

    useEffect(() => {
        const el = document.documentElement;
        if (
            effective === DEFAULT_THEME_ID ||
            !isKnownThemeId(effective, !!meta)
        ) {
            el.removeAttribute('data-theme');
        } else {
            el.setAttribute('data-theme', effective);
        }
    }, [effective, meta]);

    useEffect(() => {
        applyThemeVars(
            document.documentElement,
            preview
                ? themeToCssVars(
                      preview,
                      resolvedTheme === 'dark' ? 'dark' : 'light'
                  )
                : null
        );
    }, [preview, resolvedTheme]);

    // The fonts of the draft are served by no stylesheet yet: the editor
    // previews them from the data URIs it holds
    useEffect(() => {
        const css = preview ? themeFontFacesCss(preview) : '';
        if (!css) {
            document.getElementById(previewFontsId)?.remove();

            return;
        }
        let style = document.getElementById(previewFontsId);
        if (!style) {
            style = document.createElement('style');
            style.id = previewFontsId;
            document.head.appendChild(style);
        }
        style.textContent = css;
    }, [preview]);

    return <script dangerouslySetInnerHTML={{__html: script}} />;
}
