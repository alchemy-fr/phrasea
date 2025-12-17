import React from 'react';
import {usePublication} from '../../hooks/usePublication.ts';
import PublicationProxy from './PublicationProxy.tsx';

type Props = {
    id: string;
    assetId?: string;
};

export default function PublicationView({id}: Props) {
    const {publication, errorCode, load, loading} = usePublication({
        id,
    });

    return (
        <PublicationProxy
            publication={publication}
            loading={loading}
            errorCode={errorCode}
            load={load}
        ></PublicationProxy>
    );
}
