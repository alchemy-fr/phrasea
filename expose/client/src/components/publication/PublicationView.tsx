import React, {FC} from 'react';
import {usePublication} from '../../hooks/usePublication.ts';
import PublicationProxy from './PublicationProxy.tsx';
import {logPublicationView} from '../../api/assetApi.ts';
import PublicationStructure from './layouts/common/PublicationStructure.tsx';
import SingleAssetLayout from './layouts/single/SingleAssetLayout.tsx';
import {LayoutProps} from './layouts/types.ts';
import {layouts} from './layouts';
import {LayoutEnum, Publication} from '../../types.ts';

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

    const layout =
        (publication?.layout ? layouts[publication.layout] : undefined) ??
        layouts[LayoutEnum.Grid];

    const LayoutComponent: FC<LayoutProps> =
        publication?.assets?.length === 1 ? SingleAssetLayout : layout;

    return (
        <PublicationProxy
            publication={publication}
            loading={loading}
            errorCode={errorCode}
            load={load}
        >
            <PublicationStructure publication={publication as Publication}>
                <LayoutComponent
                    publication={publication as Publication}
                    assetId={assetId}
                />
            </PublicationStructure>
        </PublicationProxy>
    );
}
