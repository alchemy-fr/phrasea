import React from 'react';
import {Box, IconButton, Tooltip} from '@mui/material';
import {AssetTypeIcon} from '@alchemy/phrasea-framework';
import DeleteIcon from '@mui/icons-material/Delete';
import ErrorIcon from '@mui/icons-material/Error';

type Props = {
    file: File;
    error?: string;
    onRemove?: (file: File) => void;
    uploadProgress?: number;
};

export default function FileCard({
    file,
    error,
    onRemove,
    uploadProgress,
}: Props) {
    const [src, setSrc] = React.useState<string>();

    React.useEffect(() => {
        if (file.type.indexOf('image/') === 0 && file.size < 15728640) {
            const reader = new FileReader();

            reader.onload = e => {
                setSrc(e.target?.result as string);
            };
            reader.readAsDataURL(file);
        }
    }, [file]);

    const size = 150;

    return (
        <Box
            sx={theme => ({
                border: `1px solid ${theme.palette.divider}`,
                borderRadius: theme.shape.borderRadius,
                overflow: 'hidden',
                width: size,
                height: size,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                backgroundColor: theme.palette.grey[200],
                position: 'relative',
                img: {
                    width: '100%',
                    height: '100%',
                    objectFit: 'cover',
                },
                ...(error
                    ? {
                          borderColor: theme.palette.error.main,
                      }
                    : {}),
            })}
            title={file.name}
        >
            {onRemove ? (
                <IconButton
                    sx={theme => ({
                        'position': 'absolute',
                        'top': theme.spacing(0.5),
                        'right': theme.spacing(0.5),
                        'zIndex': 1,
                        'backgroundColor': theme.palette.background.paper,
                        '&:hover': {
                            backgroundColor: theme.palette.background.paper,
                        },
                    })}
                    size="small"
                    onClick={e => {
                        e.stopPropagation();
                        onRemove(file);
                    }}
                >
                    <DeleteIcon />
                </IconButton>
            ) : null}
            {error ? (
                <Tooltip title={error}>
                    <Box
                        sx={theme => ({
                            'position': 'absolute',
                            'top': theme.spacing(0.5),
                            'right': theme.spacing(0.5),
                            'zIndex': 1,
                            'backgroundColor': theme.palette.background.paper,
                            '&:hover': {
                                backgroundColor: theme.palette.background.paper,
                            },
                        })}
                    >
                        <ErrorIcon color="error" />
                    </Box>
                </Tooltip>
            ) : null}
            {undefined !== uploadProgress ? (
                <div
                    style={{
                        position: 'absolute',
                        bottom: 0,
                        right: 0,
                        height: '100%',
                        backgroundColor: 'white',
                        transition: `width 0.6s ease`,
                        opacity: 0.7,
                        width: 100 - uploadProgress + '%',
                    }}
                />
            ) : null}
            {src ? (
                <img src={src} alt={file.name} />
            ) : (
                <AssetTypeIcon mimeType={file.type} />
            )}
        </Box>
    );
}
