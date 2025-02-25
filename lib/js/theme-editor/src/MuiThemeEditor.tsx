import React, {ChangeEventHandler, useContext} from 'react';
import ThemeEditorContext from './ThemeEditorContext';
import {
    Alert,
    Box,
    Button,
    createTheme,
    TextField,
    ThemeOptions,
    Typography,
} from '@mui/material';
import {getSessionStorage} from '@alchemy/storage';

type Props = {
    onClose: () => void;
};

export default function MuiThemeEditor({onClose}: Props) {
    const themeEditorKey = 'theme-editor';
    const [error, setError] = React.useState<string | undefined>();
    const storage = getSessionStorage();
    const {setThemeOptions} = useContext(ThemeEditorContext)!;
    const [value, setValue] = React.useState(
        storage.getItem(themeEditorKey) || ''
    );
    const timeoutRef = React.useRef<ReturnType<typeof setTimeout>>();

    const onChange = React.useCallback<ChangeEventHandler<HTMLTextAreaElement>>(
        e => {
            const v = e.target.value;
            setValue(v);

            storage.setItem(themeEditorKey, v);

            if (timeoutRef.current) {
                clearTimeout(timeoutRef.current);
            }

            timeoutRef.current = setTimeout(() => {
                const code = v
                    .trim()
                    .replace(/^\s*(export\s+)?const\s+[^\s]+\s*=\s*\{/, '{');

                try {
                    const themeOptions = eval(`(function () {
                    return ${code}
                })();`) as ThemeOptions;

                    try {
                        createTheme(themeOptions);
                        setThemeOptions(themeOptions);
                        setError(undefined);
                    } catch (e: any) {
                        setError(e.toString());
                    }
                } catch (e: any) {
                    setError(`JS syntax: ${e.toString()}`);
                }
            }, 100);
        },
        [setThemeOptions, timeoutRef]
    );

    return (
        <>
            <Typography variant={'h2'}>Theme Editor</Typography>
            <Box
                sx={{
                    pt: 2,
                    pb: 2,
                }}
            >
                <TextField
                    onKeyDown={e => e.stopPropagation()}
                    onKeyPress={e => e.stopPropagation()}
                    label={`Theme Options`}
                    maxRows={20}
                    helperText={
                        <>
                            Check{' '}
                            <a
                                target={'_blank'}
                                rel={'noopener noreferrer'}
                                href={`https://bareynol.github.io/mui-theme-creator/`}
                            >
                                Playground
                            </a>
                        </>
                    }
                    multiline={true}
                    style={{
                        width: '100%',
                    }}
                    value={value}
                    onChange={onChange}
                />
            </Box>
            {error && <Alert severity={'error'}>{error}</Alert>}
            <Button onClick={onClose}>Close</Button>
        </>
    );
}
