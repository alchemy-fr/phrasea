'use client';

import {createContext, useContext} from 'react';

export type AssetViewerNavigation = {
    /** Shows another asset in the viewer, with a history entry */
    goTo: (assetId: string, options?: {renditionId?: string}) => void;
};

export const AssetViewerNavigationContext =
    createContext<AssetViewerNavigation | null>(null);

/**
 * The asset viewer the component is in, if any: an asset opened from it (a
 * similar asset, an attachment) is shown in place rather than by a navigation,
 * which would stack a second viewer over it.
 */
export function useAssetViewerNavigation(): AssetViewerNavigation | null {
    return useContext(AssetViewerNavigationContext);
}
