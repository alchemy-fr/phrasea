import {AssetOrAssetContainer} from '../../types';
import React from 'react';

export function getItemListFromEvent<Item extends AssetOrAssetContainer>(
    currentSelection: Item[],
    item: Item,
    pages: Item[][],
    e?: React.MouseEvent
): Item[] {
    if (e?.ctrlKey) {
        return currentSelection.includes(item)
            ? currentSelection.filter(i => i !== item)
            : currentSelection.concat([item]);
    }

    if (e?.shiftKey && currentSelection.length > 0) {
        let boundaries: [
            [number, number] | undefined,
            [number, number] | undefined,
        ] = [undefined, undefined];

        for (let p = 0; p < pages.length; ++p) {
            const items = pages[p];
            for (let j = 0; j < items.length; ++j) {
                const i = items[j];
                if (currentSelection.includes(i) || item === i) {
                    boundaries = [boundaries[0] ?? [p, j], [p, j]];
                }
            }
        }

        const selection = [];
        for (let i = boundaries[0]![0]; i <= boundaries[1]![0]; ++i) {
            const start = i === boundaries[0]![0] ? boundaries[0]![1] : 0;
            const end =
                i === boundaries[1]![0]
                    ? boundaries[1]![1]
                    : pages[i].length - 1;
            for (let j = start; j <= end; ++j) {
                selection.push(pages[i][j]);
            }
        }

        return selection;
    }

    return [item];
}
