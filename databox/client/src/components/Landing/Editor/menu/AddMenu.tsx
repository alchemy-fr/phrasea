import {Box, Popover} from '@mui/material';
import {Editor} from '@tiptap/core';
import IconButton from '@mui/material/IconButton';
import AddIcon from '@mui/icons-material/Add';
import {useState} from 'react';
import InsertMenu from './InsertMenu.tsx';

type Props = {
    editor: Editor;
};

export default function AddMenu({editor}: Props) {
    const [anchorEl, setAnchorEl] = useState<HTMLButtonElement | null>(null);

    const closeHandle = () => setAnchorEl(null);
    return (
        <Box
            sx={{
                position: 'absolute',
                left: -55,
                top: 0,
                transform: 'translateY(-50%)',
                zIndex: 10,
            }}
        >
            <IconButton
                onClick={e => {
                    setAnchorEl(e.currentTarget);
                }}
            >
                <AddIcon />
            </IconButton>
            <Popover
                open={Boolean(anchorEl)}
                anchorEl={anchorEl}
                onClose={closeHandle}
                anchorOrigin={{
                    vertical: 'top',
                    horizontal: 'right',
                }}
            >
                <InsertMenu editor={editor} onClose={closeHandle} />
            </Popover>
        </Box>
    );
}
