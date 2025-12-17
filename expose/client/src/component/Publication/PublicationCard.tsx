import * as React from 'react';
import Card from '@mui/material/Card';
import CardMedia from '@mui/material/CardMedia';
import CardContent from '@mui/material/CardContent';
import CardActions from '@mui/material/CardActions';
import Typography from '@mui/material/Typography';
import {Publication} from '../../types.ts';
import {Button, CardActionArea} from '@mui/material';
import {useTranslation} from 'react-i18next';

type Props = {
    publication: Publication;
    onClick: (id: string) => void;
};

export default function PublicationCard({publication, onClick}: Props) {
    const previewUrl = publication.cover?.previewUrl;
    const {t} = useTranslation();

    return (
        <Card>
            <CardActionArea onClick={() => onClick(publication.id)}>
                <CardMedia
                    component="img"
                    height="194"
                    image={previewUrl}
                    alt={publication.title}
                />
                <CardContent>
                    <Typography gutterBottom variant="h5" component="div">
                        {publication.title}
                    </Typography>
                    <Typography variant="body2" color="text.secondary">
                        {publication.description}
                    </Typography>
                </CardContent>
            </CardActionArea>
            {publication.capabilities.edit ||
                (publication.capabilities.delete && (
                    <CardActions>
                        {publication.capabilities.edit && (
                            <Button size="small" color="primary">
                                {t('publication_card.edit', 'Edit')}
                            </Button>
                        )}
                        {publication.capabilities.delete && (
                            <Button size="small" color="error">
                                {t('publication_card.delete', 'Delete')}
                            </Button>
                        )}
                    </CardActions>
                ))}
        </Card>
    );
}
