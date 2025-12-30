import * as React from 'react';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import CardActions from '@mui/material/CardActions';
import Typography from '@mui/material/Typography';
import {Button, CardActionArea} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {getPath, Link} from '@alchemy/navigation';
import {Target} from '../types.ts';
import {routes} from '../routes.ts';

type Props = {
    target: Target;
};

export default function TargetCard({target}: Props) {
    const {t} = useTranslation();

    return (
        <Card>
            <CardActionArea
                component={Link}
                to={getPath(routes.upload, {
                    id: target.id,
                })}
            >
                <CardContent>
                    <Typography gutterBottom variant="h5" component="div">
                        {target.name}
                    </Typography>
                    <Typography variant="body2" color="text.secondary">
                        {target.description}
                    </Typography>
                </CardContent>
            </CardActionArea>
            {target.capabilities.edit ||
                (target.capabilities.delete && (
                    <CardActions>
                        {target.capabilities.edit && (
                            <Button size="small" color="primary">
                                {t('publication_card.edit', 'Edit')}
                            </Button>
                        )}
                        {target.capabilities.delete && (
                            <Button size="small" color="error">
                                {t('publication_card.delete', 'Delete')}
                            </Button>
                        )}
                    </CardActions>
                ))}
        </Card>
    );
}
