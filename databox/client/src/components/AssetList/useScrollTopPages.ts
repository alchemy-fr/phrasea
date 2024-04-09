import React from 'react';
import {AssetOrAssetContainer} from '../../types.ts';

export function useScrollTopPages<Item extends AssetOrAssetContainer>(
    node: HTMLElement | undefined | null,
    pages: Item[][]
) {
    React.useLayoutEffect(() => {
        if (pages[0]) {
            node?.scrollTo({top: 0, left: 0});
        }
    }, [pages[0], node]);
}
