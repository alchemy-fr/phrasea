import {CssBaseline, GlobalStyles} from '@mui/material';

export const AppGlobalStyles = (
    <>
        <CssBaseline />
        <GlobalStyles
            styles={theme => ({
                'input:-webkit-autofill, input:-webkit-autofill:hover, input:-webkit-autofill:focus, input:-webkit-autofill:active':
                    {
                        WebkitBoxShadow: `0 0 0 30px ${theme.palette.grey[100]} inset !important`,
                        WebkitTextFillColor: `${theme.palette.text.primary} !important`,
                        caretColor: `${theme.palette.text.primary} !important`,
                    },
            })}
        />
    </>
);
