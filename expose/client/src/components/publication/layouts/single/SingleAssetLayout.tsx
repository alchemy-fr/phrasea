import React from 'react';
import {LayoutProps} from '../types.ts';
import GalleryLayout from '../gallery/GalleryLayout.tsx';

type Props = {} & LayoutProps;

export default function SingleAssetLayout({publication}: Props) {
    return <GalleryLayout publication={publication} />;
}
