import {ReactNode} from 'react';
import {Dimensions} from '../../Media/Asset/Players';
import {
    Card,
    CardActions,
    CardContent,
    CardMedia,
    Typography,
} from '@mui/material';

type Props = {
    title: ReactNode;
    info: ReactNode;
    media: ReactNode | undefined;
    actions: ReactNode;
    dimensions: Dimensions;
};

export function RenditionStructure({
    title,
    info,
    media,
    actions,
    dimensions,
}: Props) {
    return (
        <Card
            elevation={2}
            sx={{
                display: 'flex',
                mb: 2,
            }}
        >
            <CardMedia
                sx={theme => ({
                    ...dimensions,
                    display: 'flex',
                    justifyContent: 'center',
                    alignItems: 'center',
                    backgroundColor: theme.palette.grey['100'],
                })}
            >
                {media ? media : ''}
            </CardMedia>
            <CardContent
                sx={{
                    flexGrow: 1,
                }}
            >
                <Typography component="div" variant="h5">
                    {title}
                </Typography>
                <Typography component="div" variant="body1">
                    {info}
                </Typography>

                <CardActions disableSpacing>{actions}</CardActions>
            </CardContent>
        </Card>
    );
}
