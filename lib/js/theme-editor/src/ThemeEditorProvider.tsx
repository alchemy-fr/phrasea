import React, {PropsWithChildren} from 'react';
import ThemeEditorContext from "./ThemeEditorContext";
import {createTheme, ThemeOptions, ThemeProvider} from "@mui/material";
import {TThemeEditorContext} from "./types";
import {mergeDeep} from "./merge";

type Props = PropsWithChildren<{
    defaultTheme: ThemeOptions;
}>;

export default function ThemeEditorProvider({
    defaultTheme,
    children
}: Props) {
    const [themeOptions, setThemeOptions] = React.useState<ThemeOptions>({});

    const value = React.useMemo<TThemeEditorContext>(() => {
        const theme = createTheme(
            mergeDeep({}, defaultTheme, themeOptions) as ThemeOptions
        );

        return {
            theme,
            themeOptions,
            setThemeOptions: (options) => setThemeOptions(options),
        }
    }, [defaultTheme, themeOptions]);

    return <ThemeEditorContext.Provider
        value={value}
    >
        <ThemeProvider
            theme={value.theme}
        >
            {children}
        </ThemeProvider>
    </ThemeEditorContext.Provider>
}
