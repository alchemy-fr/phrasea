import {Theme, ThemeOptions} from "@mui/material";
import {createTheme} from "@mui/material/styles";
import {mergeDeep} from "./merge";
import baseTheme from "../themes/base";
import themes from '../themes';

const themeCache: Record<string, Theme> = {};

export type ThemeName = keyof typeof themes;

export function createCachedTheme(name: ThemeName): Theme {
    if (themeCache[name]) {
        return themeCache[name];
    }

    return themeCache[name] = createTheme(mergeDeep(
        {},
        baseTheme,
        themes[name],
    ) as ThemeOptions);
}
