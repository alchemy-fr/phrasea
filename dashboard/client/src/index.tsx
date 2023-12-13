import ReactDOM from 'react-dom/client';
import Root from './Root.tsx';
import React from 'react';
import {CssBaseline, GlobalStyles, ThemeOptions} from "@mui/material";
import {ThemeEditorProvider} from '@alchemy/theme-editor';

const theme: ThemeOptions = {
    typography: {
        fontFamily: '\'Montserrat\', sans-serif',
        h1: {
            fontSize: 24,
            fontWeight: 600,
        },
        h2: {
            fontSize: 19,
            fontWeight: 600,
        },
        h5: {
            fontSize: 19,
        },
    },
    palette: {
        primary: {
            main: '#003249',
            contrastText: '#e7eaea',
        },
        secondary: {
            main: '#007EA7',
        },
        common: {
            white: '#e7eaea',
        },
        background: {
            default: '#85dbff',
        }
    },
}

const scrollbarWidth = 3;

ReactDOM.createRoot(document.getElementById('root')!).render(
    <React.StrictMode>
        <ThemeEditorProvider
            defaultTheme={theme}
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
