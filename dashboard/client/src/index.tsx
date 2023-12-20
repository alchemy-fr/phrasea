import ReactDOM from 'react-dom/client';
import Root from './Root.tsx';
import React from 'react';
import {CssBaseline, GlobalStyles, responsiveFontSizes} from '@mui/material';
import {ThemeEditorProvider} from '@alchemy/theme-editor';
import {scrollbarWidth, theme} from './theme.ts';

ReactDOM.createRoot(document.getElementById('root')!).render(
    <React.StrictMode>
        <ThemeEditorProvider
            defaultTheme={theme}
            transformTheme={theme => responsiveFontSizes(theme, {})}
        >
            <CssBaseline />
            <GlobalStyles
                styles={theme => ({
                    '*': {
                        '*::-webkit-scrollbar': {
                            width: scrollbarWidth,
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
                        backgroundColor: theme.palette.common.white,
                    },
                })}
            />
            <Root />
        </ThemeEditorProvider>
    </React.StrictMode>
);
