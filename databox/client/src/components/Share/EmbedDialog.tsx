import {Box, Button, IconButton, TextField} from '@mui/material';
import {AppDialog} from '@alchemy/phrasea-ui';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {useTranslation} from 'react-i18next';
import React from 'react';
import CloseIcon from '@mui/icons-material/Close';
import RestartAltIcon from '@mui/icons-material/RestartAlt';
import CopyToClipboard from '../../lib/CopyToClipboard.tsx';
import ContentCopyIcon from '@mui/icons-material/ContentCopy';

export type EmbedProps = {
    url: string;
    title: string;
    isImage: boolean;
};
type Props = EmbedProps & StackedModalProps;

export default function EmbedDialog({
    url,
    title,
    modalIndex,
    open,
    isImage,
}: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();
    const handleFocus = (event: React.FocusEvent<HTMLInputElement>) =>
        event.currentTarget.select();

    const defaultCode = isImage
        ? `<img src="${url}" alt="${title}" style="max-width: 100%" />`
        : `<iframe width="100%" height="500" src="${url}" title="${title}" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>`;
    const [code, setCode] = React.useState(defaultCode);

    const reset = () => {
        setCode(defaultCode);
    };

    return (
        <AppDialog
            modalIndex={modalIndex}
            open={open}
            onClose={closeModal}
            title={t('share.embed.title', 'Embed Share')}
            maxWidth={'md'}
            actions={({onClose}) => (
                <>
                    <Button onClick={reset} startIcon={<RestartAltIcon />}>
                        {t('dialog.reset', 'Reset')}
                    </Button>
                    <Button onClick={onClose} startIcon={<CloseIcon />}>
                        {t('dialog.close', 'Close')}
                    </Button>
                </>
            )}
        >
            <Box
                sx={{
                    mb: 1,
                    position: 'relative',
                }}
            >
                <TextField
                    multiline
                    fullWidth
                    value={code}
                    rows={5}
                    onChange={e => setCode(e.target.value)}
                    onFocus={handleFocus}
                    inputProps={{
                        spellCheck: false,
                        sx: {
                            fontFamily: 'monospace',
                            fontSize: 12,
                            pb: 4,
                        },
                    }}
                />
                <Box
                    sx={theme => ({
                        position: 'absolute',
                        bottom: theme.spacing(1),
                        right: theme.spacing(1),
                    })}
                >
                    <CopyToClipboard>
                        {({copy}) => (
                            <IconButton
                                onClick={() => {
                                    copy(code);
                                }}
                            >
                                <ContentCopyIcon />
                            </IconButton>
                        )}
                    </CopyToClipboard>
                </Box>
            </Box>
            <div
                dangerouslySetInnerHTML={{
                    __html: code,
                }}
            />
        </AppDialog>
    );
}
