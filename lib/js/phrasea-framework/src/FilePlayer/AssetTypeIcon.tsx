import {SvgIconProps} from '@mui/material';
import React from 'react';
import {getIconFromType} from './fileIcon';

type Props = {
    mimeType: string | undefined;
} & SvgIconProps;

export default function AssetTypeIcon({mimeType, ...iconProps}: Props) {
    return React.createElement(getIconFromType(mimeType), {
        ...iconProps,
    });
}
