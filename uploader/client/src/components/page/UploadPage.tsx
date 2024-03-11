import React from 'react';
import {Target} from '../../types.ts';
import Container from '../Container';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import {getTarget} from '../../requests';
import {useParams} from '@alchemy/navigation';
import UploadStepper from '../Upload/UploadStepper.tsx';

type Props = {};

export default function UploadPage({}: Props) {
    const {id} = useParams();
    const [target, setTarget] = React.useState<Target>();

    React.useEffect(() => {
        getTarget(id).then(setTarget);
    }, []);

    if (!target) {
        return <FullPageLoader backdrop={false} />;
    }

    return (
        <Container>
            <UploadStepper target={target} />
        </Container>
    );
}
