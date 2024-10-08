import {useEffect, useState} from 'react';
import {Asset, AssetFileVersion} from '../../../types';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import ContentTab from '../Tabbed/ContentTab';
import {getAssetFileVersions} from '../../../api/asset';
import {
    AssetFileVersionCard,
    AssetFileVersionSkeleton,
} from './AssetFileVersion';
import {useTranslation} from 'react-i18next';

type Props = {
    data: Asset;
} & DialogTabProps;

const maxDimensions = {
    width: 300,
    height: 230,
};

export default function AssetFileVersions({data, onClose, minHeight}: Props) {
    const {t} = useTranslation();
    const [versions, setVersions] = useState<AssetFileVersion[]>();

    useEffect(() => {
        getAssetFileVersions(data.id).then(d => setVersions(d.result));
    }, []);

    return (
        <ContentTab
            onClose={onClose}
            minHeight={minHeight}
            disableGutters={true}
        >
            {data.source && (
                <AssetFileVersionCard
                    asset={data}
                    version={{
                        file: data.source,
                        asset: data,
                        id: 'current',
                        name: t('asset_file_versions.current', `Current`),
                        createdAt: '',
                    }}
                    dimensions={maxDimensions}
                />
            )}
            {versions &&
                versions.map(v => {
                    return (
                        <AssetFileVersionCard
                            key={v.id}
                            asset={data}
                            version={v}
                            dimensions={maxDimensions}
                        />
                    );
                })}
            {!versions &&
                [0, 1, 2].map(i => (
                    <AssetFileVersionSkeleton
                        key={i}
                        dimensions={maxDimensions}
                    />
                ))}
        </ContentTab>
    );
}
