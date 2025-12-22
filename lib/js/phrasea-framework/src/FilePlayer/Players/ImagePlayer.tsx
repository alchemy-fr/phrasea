
import React from 'react';
import {FilePlayerProps} from '../types';

type Props = FilePlayerProps;

export default function ImagePlayer({
    file,
    title,
    onLoad,
}: Props) {
    const isSvg = file.type === 'image/svg+xml';

  return (
        <img
            style={{
                maxWidth: '100%',
                maxHeight: '100%',
                display: 'block',
                ...(isSvg ? {width: '100%'} : {}),
            }}
            crossOrigin="anonymous"
            src={file.url}
            alt={title}
            onLoad={onLoad}
        />
    );
}
