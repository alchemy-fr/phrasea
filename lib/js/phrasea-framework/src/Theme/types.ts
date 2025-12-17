import {themes} from './theme';
import type {Theme, ThemeOptions} from '@mui/material';

export type ThemeName = keyof typeof themes;

export type TThemeEditorContext = {
    theme: Theme;
    themeOptions: ThemeOptions;
    setThemeOptions: (theme: ThemeOptions) => void;
};
