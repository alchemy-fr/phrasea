import * as React from 'react';
import Card from '@mui/material/Card';
import CardHeader from '@mui/material/CardHeader';
import CardMedia from '@mui/material/CardMedia';
import CardContent from '@mui/material/CardContent';
import CardActions from '@mui/material/CardActions';
import IconButton from '@mui/material/IconButton';
import Typography from '@mui/material/Typography';
import FavoriteIcon from '@mui/icons-material/Favorite';
import ShareIcon from '@mui/icons-material/Share';
import MoreVertIcon from '@mui/icons-material/MoreVert';
import {Publication} from '../../types.ts';

type Props = {
    publication: Publication;
    onClick: (id: string) => void;
};

export default function PublicationCard({publication, onClick}: Props) {
    const previewUrl = publication.cover?.previewUrl;

    return (
        <Card sx={{maxWidth: 345}} onClick={() => onClick(publication.id)}>
            <CardHeader
                action={
                    <IconButton aria-label="settings">
                        <MoreVertIcon />
                    </IconButton>
                }
                title={publication.title}
                subheader={publication.date}
            />
            {previewUrl ? (
                <CardMedia
                    component="img"
                    height="194"
                    image={previewUrl}
                    alt={publication.title}
                />
            ) : null}
            <CardContent>
                <Typography variant="body2" color="text.secondary">
                    {publication.description}
                </Typography>
            </CardContent>
            <CardActions disableSpacing>
                <IconButton aria-label="add to favorites">
                    <FavoriteIcon />
                </IconButton>
                <IconButton aria-label="share">
                    <ShareIcon />
                </IconButton>
            </CardActions>
        </Card>
    );
}
