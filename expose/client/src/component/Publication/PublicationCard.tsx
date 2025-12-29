import * as React from 'react';
import Card from '@mui/material/Card';
import CardMedia from '@mui/material/CardMedia';
import CardContent from '@mui/material/CardContent';
import CardActions from '@mui/material/CardActions';
import Typography from '@mui/material/Typography';
import {Publication} from '../../types.ts';
import {Button, CardActionArea} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {getPath, Link} from '@alchemy/navigation';
import {routes} from '../../routes.ts';

type Props = {
    publication: Publication;
};

export default function PublicationCard({publication}: Props) {
    const previewUrl = publication.cover?.previewUrl;
    const {t} = useTranslation();

    return (
        <Card>
            <CardActionArea
                component={Link}
                to={getPath(routes.publication, {
                    id: publication.slug || publication.id,
                })}
            >
                <CardMedia
                    component="img"
                    height="194"
                    image={
                        previewUrl ??
                        'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/wcAAgMBgOb3pAAAAABJRU5ErkJggg=='
                    }
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
