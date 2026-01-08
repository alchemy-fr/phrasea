import React from 'react';
import {Target} from '../types.ts';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import {useParams} from '@alchemy/navigation';
import UploadStepper from '../components/Upload/UploadStepper.tsx';
import {getTarget} from '../api/targetApi.ts';

type Props = {};

export default function UploadPage({}: Props) {
    const {id} = useParams();
    const [target, setTarget] = React.useState<Target>();

    React.useEffect(() => {
        getTarget(id!).then(setTarget);
    }, [id]);

    if (!target) {
        return <FullPageLoader backdrop={false} />;
    }

    return <UploadStepper target={target} />;
}
