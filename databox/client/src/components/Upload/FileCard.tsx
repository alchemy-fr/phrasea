import Typography from '@mui/material/Typography';
import {FileBlobThumb} from '../../lib/upload/fileBlob';
import {Box, LinearProgress, Paper} from '@mui/material';
import byteSize from 'byte-size';

import {thumbSx} from '../Media/Asset/AssetThumb.tsx';
import AssetFileIcon from '../Media/Asset/AssetFileIcon.tsx';
import {ReactNode} from 'react';
import assetClasses from '../AssetList/classes.ts';

type Props = {
    file: File;
    actions?: ReactNode;
    size?: number;
    progress?: number;
};

export type {Props as FileCardProps};

export default function FileCard({file, progress, actions, size = 100}: Props) {
    return (
        <Paper
            sx={theme => ({
                margin: 'auto',
                ...thumbSx(size, theme),
            })}
        >
            <Box
                sx={{
                    display: 'flex',
                    flexDirection: 'row',
                }}
            >
                <div className={assetClasses.thumbWrapper}>
                    {[
                        'image/jpeg',
                        'image/png',
                        'image/bmp',
                        'image/gif',
                        'image/webp',
                        'image/svg+xml',
                    ].includes(file.type) ? (
                        <FileBlobThumb file={file} size={size} />
                    ) : (
                        <div
                            style={{
                                width: size,
                                height: size,
                                objectFit: 'contain',
                            }}
                        >
                            <AssetFileIcon mimeType={file.type} />
                        </div>
                    )}
                </div>
                <Box
                    sx={{
                        p: 2,
                        flexGrow: 1,
                    }}
                >
                    <Typography
                        sx={{
                            overflow: 'hidden',
                            textOverflow: 'ellipsis',
                            display: '-webkit-box',
                            WebkitLineClamp: '2',
                            WebkitBoxOrient: 'vertical',
                            lineHeight: 1.2,
                            wordBreak: 'break-all',
                        }}
                        gutterBottom
                        variant="subtitle1"
                    >
                        {file.name}
                    </Typography>
                    <Typography variant="body2" gutterBottom>
                        {byteSize(file.size).toString()} • {file.type}
                    </Typography>
                    {actions}
                </Box>
            </Box>
            {progress !== undefined && progress < 1 ? (
                <LinearProgress variant="determinate" value={progress * 100} />
            ) : null}
        </Paper>
    );
}
