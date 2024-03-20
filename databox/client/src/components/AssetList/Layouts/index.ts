import GridLayout from "./Grid/GridLayout.tsx";
import React from "react";
import {LayoutProps} from "../types.ts";

export enum Layout {
    List,
    Grid,
}

export const layouts: Record<Layout, React.FC<LayoutProps<any>>> = {
 [Layout.Grid]: GridLayout,
 [Layout.List]: GridLayout,
}
