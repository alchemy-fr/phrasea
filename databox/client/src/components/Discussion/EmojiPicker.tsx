import data from '@emoji-mart/data';
import Picker from '@emoji-mart/react';
import React from 'react';
import {Button, ClickAwayListener, IconButton, Popover} from '@mui/material';
import EmojiEmotionsIcon from '@mui/icons-material/EmojiEmotions';
import {stopPropagation} from '../../lib/stdFuncs.ts';
import {useTranslation} from 'react-i18next';
import CloseIcon from '@mui/icons-material/Close';

type Props = {
    onSelect?: (emoji: string | null) => void;
    value?: string;
    disabled?: boolean;
};

export default function EmojiPicker({onSelect, value, disabled}: Props) {
    const [anchor, setAnchor] = React.useState<HTMLButtonElement | null>(null);
    const {t} = useTranslation();
    const close = () => setAnchor(null);

    const open = Boolean(anchor);

    return (
        <>
            <IconButton
                sx={{
                    color: 'inherit',
                }}
                disabled={disabled}
                onClick={e => {
                    e.stopPropagation();
                    setAnchor(p =>
                        p ? null : (e.target as HTMLButtonElement)
                    );
                }}
            >
                {value ?? <EmojiEmotionsIcon />}
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
                        {onSelect && value ? (
                            <Button
                                variant={'outlined'}
                                fullWidth={true}
                                startIcon={<CloseIcon />}
                                onClick={() => {
                                    onSelect(null);
                                    close();
                                }}
                            >
                                {t('common.clear', 'Clear')}
                            </Button>
                        ) : null}
                    </div>
                </Popover>
            </ClickAwayListener>
        </>
    );
}
