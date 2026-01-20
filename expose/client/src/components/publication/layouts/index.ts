import {LayoutEnum} from '../../../types.ts';
import {FC} from 'react';
import {LayoutProps} from './types.ts';
import GridLayout from './grid/GridLayout.tsx';
import GalleryLayout from './gallery/GalleryLayout.tsx';
import DownloadLayout from './download/DownloadLayout.tsx';

export const layouts: Record<LayoutEnum, FC<LayoutProps>> = {
    [LayoutEnum.Grid]: GridLayout,
    [LayoutEnum.Gallery]: GalleryLayout,
    [LayoutEnum.Download]: DownloadLayout,
    [LayoutEnum.Mapbox]: GridLayout,
};
