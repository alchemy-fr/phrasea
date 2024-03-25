import GridLayout from "./Grid/GridLayout.tsx";
import React from "react";
import {LayoutProps} from "../types.ts";
import ListLayout from "./List/ListLayout.tsx";

export enum Layout {
    List = 'l',
    Grid = 'g',
}

export const layouts: Record<Layout, React.FC<LayoutProps<any>>> = {
 [Layout.Grid]: GridLayout,
 [Layout.List]: ListLayout,
}
