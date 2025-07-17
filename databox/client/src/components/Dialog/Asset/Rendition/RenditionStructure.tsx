import {ReactNode} from 'react';
import {Dimensions} from '../../../Media/Asset/Players';
import {Box, Card, CardContent, CardMedia, Typography} from '@mui/material';

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
                <Box sx={{display: 'flex', gap: 1, flexDirection: 'row'}}>
                    <Typography
                        component="div"
                        variant="h5"
                        style={{flexGrow: 1}}
                    >
                        {title}
                    </Typography>
                    <div>{actions}</div>
                </Box>
                <Typography component="div" variant="body1">
                    {info}
                </Typography>
            </CardContent>
        </Card>
    );
}
