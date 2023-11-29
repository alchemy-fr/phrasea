import React from 'react';
import {useParams} from "react-router";
import EmbeddedAsset from "../component/EmbeddedAsset.tsx";

type Props = {};

export default function EmbeddedAssetPage({}: Props) {
    const {id} = useParams();

    return <EmbeddedAsset
        id={id}
    />
}
