import PublicationHeader from '../../../layouts/shared-components/PublicationHeader.tsx';
import React from 'react';
import {Publication} from '../../../../types.ts';

type Props = {
    data: Publication;
};

export default function GridLayout({data}: Props) {
    return (
        <>
            <PublicationHeader data={data} />
        </>
    );
}
