import {ThemeOptions} from '@mui/material';
import {mergeDeep} from '../../../core/src/merge';
import baseTheme from './themes/base';
import defaultTheme from './themes/default';
import oneTheme from './themes/one';
import twoTheme from './themes/two';
import threeTheme from './themes/three';
import fourTheme from './themes/four';
import {ThemeName} from './types';

const themeCache: Record<string, ThemeOptions> = {};

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

export const themes = {
    default: defaultTheme,
    one: oneTheme,
    two: twoTheme,
    three: threeTheme,
    four: fourTheme,
};
