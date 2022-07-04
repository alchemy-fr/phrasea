import React, {useEffect, useState} from 'react';
import {Asset, AssetRendition} from "../../../types";
import {DialogTabProps} from "../Tabbed/TabbedDialog";
import ContentTab from "../Tabbed/ContentTab";
import {getAssetRenditions} from "../../../api/rendition";
import {Rendition, RenditionSkeleton} from "./Rendition";

type Props = {
    data: Asset;
} & DialogTabProps;

const maxDimensions = {
    width: 300,
    height: 230,
}

export default function Renditions({
                                       data,
                                       onClose,
                                       minHeight,
                                   }: Props) {
    const [renditions, setRenditions] = useState<AssetRendition[]>();

    useEffect(() => {
        getAssetRenditions(data.id).then(d => setRenditions(d.result));
    }, []);

    return <ContentTab
        onClose={onClose}
        minHeight={minHeight}
        disableGutters={true}
    >
        {renditions && renditions.map(r => {
            return <Rendition
                key={r.id}
                    rendition={r}
                    title={data.resolvedTitle}
                    maxDimensions={maxDimensions}
                />
        })}
        {!renditions && [0, 1, 2].map(i => <RenditionSkeleton
            key={i}
            maxDimensions={maxDimensions}
        />)}
    </ContentTab>
}
