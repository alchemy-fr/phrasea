import * as React from 'react';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Typography from '@mui/material/Typography';
import {CardActionArea} from '@mui/material';
import {getPath, Link} from '@alchemy/navigation';
import {Target} from '../types.ts';
import {routes} from '../routes.ts';

type Props = {
    target: Target;
};

export default function TargetCard({target}: Props) {
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
        </Card>
    );
}
