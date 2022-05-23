import React, {MouseEvent} from 'react';
import {Asset} from "../../../types";
import Thumb from "./Thumb";

type Props = {
    selected?: boolean;
    displayAttributes: boolean;
    onClick?: (id: string, e: MouseEvent) => void;
    thumbSize: number;
} & Asset;

export default function AssetThumb({

                                       resolvedTitle,
                                       thumbSize,
                                       thumbnail,
                                       thumbnailActive,
                                       selected,
                                   }: Props) {


    return <Thumb
        selected={selected}
        size={thumbSize}
    >
        {thumbnail && <img src={thumbnail.url} alt={resolvedTitle}/>}
        {thumbnailActive && <img
            src={thumbnailActive.url}
            alt={resolvedTitle}
            className={'ta'}
        />}
    </Thumb>
}
