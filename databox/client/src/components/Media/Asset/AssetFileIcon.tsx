import {SvgIconProps} from '@mui/material';
import React from 'react';
import assetClasses from '../../AssetList/classes';
import {getIconFromType} from '@alchemy/phrasea-framework';

type Props = {
    mimeType: string | undefined;
} & SvgIconProps;

export default function AssetFileIcon({mimeType, ...iconProps}: Props) {
    return React.createElement(getIconFromType(mimeType), {
        fontSize: 'large',
        className: assetClasses.fileIcon,
        ...iconProps,
    });
}
