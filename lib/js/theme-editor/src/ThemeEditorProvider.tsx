import React, {PropsWithChildren} from 'react';
import ThemeEditorContext from './ThemeEditorContext';
import {createTheme, Theme, ThemeOptions, ThemeProvider} from '@mui/material';
import {TThemeEditorContext} from './types';
import {mergeDeep} from './merge';

type Props = PropsWithChildren<{
    defaultTheme: ThemeOptions;
    transformTheme?: (theme: Theme) => Theme;
}>;

export default function ThemeEditorProvider({
    defaultTheme,
    transformTheme,
    children,
}: Props) {
    const [themeOptions, setThemeOptions] = React.useState<ThemeOptions>({});

    const value = React.useMemo<TThemeEditorContext>(() => {
        const theme = createTheme(
            mergeDeep(
                {},
                defaultTheme,
                (window as any).config?.muiTheme ?? {},
                themeOptions
            ) as ThemeOptions
        );

        return {
            theme: transformTheme ? transformTheme(theme) : theme,
            themeOptions,
            setThemeOptions: options => setThemeOptions(options),
        };
    }, [defaultTheme, themeOptions]);

    return (
        <ThemeEditorContext.Provider value={value}>
            <ThemeProvider theme={value.theme}>{children}</ThemeProvider>
        </ThemeEditorContext.Provider>
    );
}
