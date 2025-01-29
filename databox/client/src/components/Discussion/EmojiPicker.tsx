import data from '@emoji-mart/data';
import Picker from '@emoji-mart/react';
import React from 'react';
import {ClickAwayListener, IconButton, Popover} from '@mui/material';
import EmojiEmotionsIcon from '@mui/icons-material/EmojiEmotions';
import {stopPropagation} from '../../lib/stdFuncs.ts';

type Props = {
    onSelect?: (emoji: string) => void;
    disabled?: boolean;
};

export default function EmojiPicker({onSelect, disabled}: Props) {
    const [anchor, setAnchor] = React.useState<HTMLButtonElement | null>(null);
    const close = () => setAnchor(null);

    const open = Boolean(anchor);

    return (
        <>
            <IconButton
                disabled={disabled}
                onClick={e => {
                    e.stopPropagation();
                    setAnchor(p =>
                        p ? null : (e.target as HTMLButtonElement)
                    );
                }}
            >
                <EmojiEmotionsIcon />
            </IconButton>

            <ClickAwayListener onClickAway={close}>
                <Popover
                    open={open}
                    anchorEl={anchor}
                    onClose={close}
                    anchorOrigin={{
                        vertical: 'bottom',
                        horizontal: 'right',
                    }}
                >
                    <div onClick={stopPropagation}>
                        <Picker
                            data={data}
                            onEmojiSelect={(e: any) => {
                                onSelect?.(e.native);
                                close();
                            }}
                            previewPosition={'none'}
                        />
                    </div>
                </Popover>
            </ClickAwayListener>
        </>
    );
}
