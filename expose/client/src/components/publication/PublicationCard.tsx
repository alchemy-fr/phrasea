import * as React from 'react';
import Card from '@mui/material/Card';
import CardMedia from '@mui/material/CardMedia';
import CardContent from '@mui/material/CardContent';
import CardActions from '@mui/material/CardActions';
import Typography from '@mui/material/Typography';
import {Publication} from '../../types.ts';
import {Button, CardActionArea, Tooltip} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {Link} from '@alchemy/navigation';
import {getTranslatedDescription, getTranslatedTitle} from '../../i18n.ts';
import VisibilityOffIcon from '@mui/icons-material/VisibilityOff';
import {getPublicationPath} from '../../hooks/useNavigateToPublication.ts';
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
                to={getPublicationPath(publication)}
            >
                {(!publication.enabled || !publication.publiclyListed) && (
                    <Tooltip
                        sx={theme => ({
                            position: 'absolute',
                            zIndex: 1,
                            top: theme.spacing(1),
                            right: theme.spacing(1),
                            color: theme.palette.error.main,
                        })}
                        title={
                            <>
                                {!publication.enabled && (
                                    <div>
                                        {t(
                                            'publication_card.disabled_tooltip',
                                            'This publication is disabled, only operators can access it.'
                                        )}
                                    </div>
                                )}
                                {!publication.publiclyListed && (
                                    <div>
                                        {t(
                                            'publication_card.unlisted',
                                            'This publication is not listed, you can see it because you have such permisions'
                                        )}
                                    </div>
                                )}
                            </>
                        }
                    >
                        <VisibilityOffIcon />
                    </Tooltip>
                )}
                <CardMedia
                    component="img"
                    height="194"
                    image={
                        previewUrl ??
                        'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMSIgaGVpZ2h0PSIxIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxIDEiPjwvc3ZnPg=='
                    }
                    sx={theme => ({
                        bgcolor: theme.palette.grey[200],
                        ...(!publication.enabled
                            ? {
                                  opacity: 0.5,
                              }
                            : {}),
                    })}
                    alt={publication.title}
                />
                <CardContent>
                    <Typography gutterBottom variant="h5" component="div">
                        {getTranslatedTitle(publication)}
                    </Typography>
                    <Typography variant="body2" color="text.secondary">
                        {getTranslatedDescription(publication)}
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
