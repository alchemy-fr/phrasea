import {AssetOrAssetContainer} from "../../../types.ts";


export function getPage<Item extends AssetOrAssetContainer>(pages: Item[][], index: number): {
    item: Item;
    pageIndex: number;
    itemIndex: number;
} {
    let pageIndex = 0;
    let itemIndex = 0;

    let i = 0;
    while (i < index) {
        itemIndex++;
        i++;
        if (itemIndex === pages[pageIndex].length) {
            pageIndex++;
            itemIndex = 0;
        }
    }
    const item = pages[pageIndex][itemIndex]!;

    return {
        item,
        pageIndex,
        itemIndex,
    }
}
