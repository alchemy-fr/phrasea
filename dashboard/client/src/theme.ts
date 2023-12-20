import {ThemeOptions} from '@mui/material';

export const theme: ThemeOptions = {
    typography: {
        fontFamily: "'Montserrat', sans-serif",
        h1: {
            fontSize: '3rem',
            fontWeight: 600,
        },
        h2: {
            fontWeight: 600,
            fontSize: '1.2rem',
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
        },
    },
};

export const scrollbarWidth = 3;
