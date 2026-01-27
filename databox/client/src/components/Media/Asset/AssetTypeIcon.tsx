import {SvgIconProps} from '@mui/material';
import {getIconFromType} from '@alchemy/phrasea-framework';
import React from 'react';

type Props = {
    mimeType: string | undefined;
} & SvgIconProps;

export default function AssetTypeIcon({mimeType, ...iconProps}: Props) {
    return React.createElement(getIconFromType(mimeType), {
        ...iconProps,
    });
}
