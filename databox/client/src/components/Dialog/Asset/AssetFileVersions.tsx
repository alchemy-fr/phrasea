import React, {useEffect, useState} from 'react';
import {Asset, AssetFileVersion} from "../../../types";
import {DialogTabProps} from "../Tabbed/TabbedDialog";
import ContentTab from "../Tabbed/ContentTab";
import {getAssetFileVersions} from "../../../api/asset";
import {AssetFileVersionCard, AssetFileVersionSkeleton} from "./AssetFileVersion";

type Props = {
    data: Asset;
} & DialogTabProps;

const maxDimensions = {
    width: 300,
    height: 230,
}

export default function AssetFileVersions({
                                       data,
                                       onClose,
                                       minHeight,
                                   }: Props) {
    const [versions, setVersions] = useState<AssetFileVersion[]>();

    useEffect(() => {
        getAssetFileVersions(data.id).then(d => setVersions(d.result));
    }, []);

    return <ContentTab
        onClose={onClose}
        minHeight={minHeight}
        disableGutters={true}
    >
        {versions && versions.map(v => {
            return <AssetFileVersionCard
                key={v.id}
                asset={data}
                version={v}
                maxDimensions={maxDimensions}
            />
        })}
        {!versions && [0, 1, 2].map(i => <AssetFileVersionSkeleton
            key={i}
            maxDimensions={maxDimensions}
        />)}
    </ContentTab>
}
