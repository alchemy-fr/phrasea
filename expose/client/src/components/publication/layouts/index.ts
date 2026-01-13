import {LayoutEnum} from '../../../types.ts';
import {FC} from 'react';
import {LayoutProps} from './types.ts';
import GridLayout from './grid/GridLayout.tsx';

export const layouts: Record<LayoutEnum, FC<LayoutProps>> = {
    [LayoutEnum.Grid]: GridLayout,
    [LayoutEnum.Gallery]: GridLayout,
    [LayoutEnum.Mapbox]: GridLayout,
    [LayoutEnum.Download]: GridLayout,
};
