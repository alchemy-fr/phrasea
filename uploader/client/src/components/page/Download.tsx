import React from 'react';
import Container from '../Container.jsx';
import AssetForm from '../AssetForm.jsx';
import {getPath, Link, useNavigate, useParams} from '@alchemy/navigation';
import {routes} from '../../routes.ts';

type Props = {};

export default function Download({}: Props) {
    const [done, setDone] = React.useState(false);
    const navigate = useNavigate();
    const {id} = useParams();

    const baseSchema = {
        required: ['url'],
        properties: {
            url: {
                title: 'Asset URL',
                type: 'string',
                widget: 'url',
            },
        },
    };

    return (
        <Container>
            <div>
                <Link to={getPath(routes.index)}>Back</Link>
            </div>

            {done ? (
                <h3>Your file will be downloaded!</h3>
            ) : (
                <AssetForm
                    targetId={id}
                    submitPath={'/downloads'}
                    baseSchema={baseSchema}
                    onComplete={() => setDone(true)}
                    onCancel={() => navigate(getPath(routes.index))}
                />
            )}
        </Container>
    );
}
