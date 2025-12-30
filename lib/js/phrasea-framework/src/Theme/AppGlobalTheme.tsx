import {
    CssBaseline,
    GlobalStyles, GlobalStylesProps,
    SxProps,
    Theme,
    ThemeOptions,
} from '@mui/material';
import baseTheme from './themes/base';
import ThemeEditorProvider from './ThemeEditor/ThemeEditorProvider';
import {PropsWithChildren} from 'react';
import {resolveSx} from '@alchemy/core';

type Props = PropsWithChildren<{
    scrollbarWidth?: number;
    themeOptions?: ThemeOptions;
    styles?: SxProps<Theme>;
}>;

export function AppGlobalTheme({
    children,
    styles,
    themeOptions,
    scrollbarWidth = 8
}: Props) {
    return (
        <ThemeEditorProvider defaultTheme={themeOptions ?? baseTheme}>
            <CssBaseline />
            <GlobalStyles
                styles={theme => {
                    return {
                        'a': {
                            color: theme.palette.primary.main,
                            textDecorationColor: theme.palette.primary.main,
                        },
                        'input:-webkit-autofill, input:-webkit-autofill:hover, input:-webkit-autofill:focus, input:-webkit-autofill:active':
                            {
                                WebkitBoxShadow: `0 0 0 30px ${theme.palette.grey[100]} inset !important`,
                                WebkitTextFillColor: `${theme.palette.text.primary} !important`,
                                caretColor: `${theme.palette.text.primary} !important`,
                            },
                        '*': {
                            '*::-webkit-scrollbar': {
                                width: scrollbarWidth,
                                height: scrollbarWidth,
                            },
                            '*::-webkit-scrollbar-track': {
                                borderRadius: 10,
                            },
                            '*::-webkit-scrollbar-thumb': {
                                borderRadius: scrollbarWidth,
                                backgroundColor: theme.palette.primary.main,
                            },
                        },
                        'body': {
                            backgroundColor: theme.palette.background.default,
                        },
                        ...(styles
                            ? resolveSx(styles, theme)
                            : {}),
                    } as GlobalStylesProps['styles'];
                }}
            />
            {children}
        </ThemeEditorProvider>
    );
}
