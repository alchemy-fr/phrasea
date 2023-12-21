import {ThemeOptions} from '@mui/material';
import {mergeDeep} from './merge';
import baseTheme from '../themes/base';
import themes from '../themes';

const themeCache: Record<string, ThemeOptions> = {};

export type ThemeName = keyof typeof themes;

export function createCachedThemeOptions(name: ThemeName): ThemeOptions {
    if (themeCache[name]) {
        return themeCache[name];
    }

    return (themeCache[name] = mergeDeep(
        {},
        baseTheme,
        themes[name]
    ) as ThemeOptions);
}
