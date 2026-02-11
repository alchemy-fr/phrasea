import {useTranslation} from 'react-i18next';
import {
    Box,
    ListItem,
    ListItemButton,
    ListItemText,
    Paper,
    TextField,
} from '@mui/material';
import {Editor} from '@tiptap/core';
import {widgets} from '../../widgets';
import {useMemo, useState} from 'react';

type Element = {
    name: string;
    title: string;
};

type Props = {
    editor: Editor;
    onClose: () => void;
};

export default function InsertMenu({editor, onClose}: Props) {
    const {t} = useTranslation();
    const [query, setQuery] = useState('');

    const elements = useMemo<Element[]>(() => {
        return widgets.map(w => ({
            name: w.name,
            title: w.getTitle(t),
        }));
    }, [t]);

    return (
        <Paper>
            <Box
                sx={{
                    p: 1,
                }}
            >
                <TextField
                    autoFocus={true}
                    value={query}
                    variant={'standard'}
                    onChange={e => setQuery(e.target.value)}
                    placeholder={t(
                        'landing.editor.insert_menu.search_placeholder',
                        'Search widgets...'
                    )}
                    fullWidth
                />
            </Box>
            {elements
                .filter(e =>
                    e.title.toLowerCase().includes(query.toLowerCase())
                )
                .map(e => (
                    <ListItem key={e.name} disablePadding>
                        <ListItemButton
                            onClick={() => {
                                onClose();
                                editor
                                    .chain()
                                    .focus()
                                    .setWidget({
                                        widget: e.name,
                                    })
                                    .run();
                            }}
                        >
                            <ListItemText primary={e.title} />
                        </ListItemButton>
                    </ListItem>
                ))}
        </Paper>
    );
}
