import React from 'react';
import {usePublication} from '../../hooks/usePublication.ts';
import PublicationProxy from './PublicationProxy.tsx';
import GridLayout from './layouts/grid/GridLayout.tsx';
import {logPublicationView} from '../../api/assetApi.ts';

type Props = {
    id: string;
    assetId?: string;
};

export default function PublicationView({id, assetId}: Props) {
    const {publication, errorCode, load, loading} = usePublication({
        id,
    });

    React.useEffect(() => {
        if (publication) {
            logPublicationView(publication.id);
        }
    }, [publication]);

    return (
        <PublicationProxy
            publication={publication}
            loading={loading}
            errorCode={errorCode}
            load={load}
        >
            <GridLayout publication={publication!} assetId={assetId} />
        </PublicationProxy>
    );
}
