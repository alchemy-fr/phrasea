import {ReactNode} from 'react';
import {Dimensions} from '@alchemy/core';
import {Box, Card, CardContent, CardMedia, Typography} from '@mui/material';
import {getMediaBackgroundColor} from '../../../uiVars.ts';

type Props = {
    name: ReactNode;
    info: ReactNode;
    media: ReactNode | undefined;
    actions: ReactNode;
    dimensions: Dimensions;
};

export function RenditionStructure({
    name,
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
                    ...getMediaBackgroundColor(theme),
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
                        {name}
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
