import type {Theme, ThemeOptions} from '@mui/material';

export type TThemeEditorContext = {
    theme: Theme;
    themeOptions: ThemeOptions;
    setThemeOptions: (theme: ThemeOptions) => void;
};
