import GridLayout from './Grid/GridLayout';
import React from 'react';
import {LayoutProps} from '../types';
import ListLayout from './List/ListLayout';
import 'react-virtualized/styles.css';
import {Layout} from './layout';

export {Layout};

export const layouts: Record<Layout, React.FC<LayoutProps<any>>> = {
    [Layout.Grid]: GridLayout,
    [Layout.List]: ListLayout,
};
