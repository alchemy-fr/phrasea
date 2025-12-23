import React from 'react';
import {Classes} from '../types.ts';
import {AssetTypeIcon} from '@alchemy/phrasea-framework';
import {Theme} from '@mui/material';

type Props = {
    style?: React.CSSProperties;
    mimeType: string;
};

export default function AssetIconThumbnail({style, mimeType}: Props) {
    return (
        <div className={Classes.thumbIconContainer} style={style}>
            <AssetTypeIcon mimeType={mimeType} />
        </div>
    );
}

export const thumbSx = (theme: Theme, fontSize = 48) => {
    return {
        [`.${Classes.thumbIconContainer}`]: {
            'display': 'flex',
            'alignItems': 'center',
            'justifyContent': 'center',
            'color': theme.palette.text.secondary,
            '.MuiSvgIcon-root': {
                fontSize: `${fontSize}px`,
            },
        },
    };
};
