import Button from '@mui/material/Button';
import Typography from '@mui/material/Typography';
import {FileBlobThumb} from '../../lib/upload/fileBlob';
import {Grid, Paper} from '@mui/material';
import byteSize from 'byte-size';

import {thumbSx} from '../Media/Asset/AssetThumb.tsx';
import {useTranslation} from 'react-i18next';

const size = 100;

type Props = {
    file: File;
    onRemove: () => void;
};

export default function FileCard({file, onRemove}: Props) {
    const {t} = useTranslation();
    return (
        <Paper
            sx={theme => ({
                padding: theme.spacing(2),
                margin: 'auto',
                ...thumbSx(size, theme),
            })}
        >
            <Grid
                sx={theme => ({
                    width: {
                        xs: `calc(${size}px + ${theme.spacing(2)})`,
                        sm: 395,
                    },
                })}
                container
                spacing={2}
            >
                <Grid item>
                    {[
                        'image/jpeg',
                        'image/png',
                        'image/bmp',
                        'image/gif',
                    ].includes(file.type) ? (
                        <FileBlobThumb file={file} size={size} />
                    ) : (
                        <div
                            style={{
                                width: 0,
                                height: size,
                                objectFit: 'contain',
                            }}
                        />
                    )}
                </Grid>
                <Grid item xs={12} sm>
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
                    <Button size="small" color="error" onClick={onRemove}>
                        {t('common.remove', `Remove`)}
                    </Button>
                </Grid>
            </Grid>
        </Paper>
    );
}
