import GridLayout from './Grid/GridLayout';
import React from 'react';
import {LayoutProps} from '../types';
import ListLayout from './List/ListLayout';
import MasonryLayout from './Masonry/MasonryLayout';

export enum Layout {
    List = 'l',
    Grid = 'g',
    Masonry = 'm',
}

export const layouts: Record<Layout, React.FC<LayoutProps<any>>> = {
    [Layout.Grid]: GridLayout,
    [Layout.List]: ListLayout,
    [Layout.Masonry]: MasonryLayout,
};
