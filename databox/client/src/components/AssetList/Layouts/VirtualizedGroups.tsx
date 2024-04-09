import React, {ForwardedRef, ReactNode, RefObject} from 'react';
import {Asset, AssetOrAssetContainer, GroupValue} from '../../../types';
import {ItemToAssetFunc} from "../types.ts";
import GroupDivider from "./GroupDivider.tsx";
import {CellMeasurerCache} from "react-virtualized";

type GroupSet = {
    index: number;
    value: GroupValue;
    key: string | null;
}

type Props<Item extends AssetOrAssetContainer> = {
    pages: Item[][];
    itemToAsset: ItemToAssetFunc<Item> | undefined;
    cellMeasurer: CellMeasurerCache;
    height: number;
};

export default React.forwardRef<HTMLDivElement, Props<any>>(function VirtualizedGroups<Item extends AssetOrAssetContainer>({
    pages,
    height,
    itemToAsset,
    cellMeasurer,
}: Props<Item>, ref: ForwardedRef<HTMLDivElement>) {
    const [_inc, setInc] = React.useState(0);
    const {groups, rowCount} = React.useMemo(() => {
        const groups: GroupSet[] = [];

        const all = pages.flat();
        const rowCount = all.length;
        all.map((item, index) => {
            const asset: Asset = itemToAsset
                ? itemToAsset(item)
                : (item as unknown as Asset);

            const {groupValue} = asset;

            if (groupValue) {
                groups.push({
                    value: groupValue,
                    key: groupValue.key,
                    index,
                });
            }
        });

        return {groups, rowCount};
    }, [pages, cellMeasurer]);

    React.useEffect(() => {
        const onScroll = () => {
            setInc(p => p + 1);
        };

        (ref as RefObject<HTMLDivElement>).current?.addEventListener('scroll', onScroll);

        return () => {
            (ref as RefObject<HTMLDivElement>).current?.removeEventListener('scroll', onScroll);
        }
    }, [ref]);

    const divs: ReactNode[] = [];
    let groupIndex = 0;
    let i = 0;
    while (groups[groupIndex] && i < rowCount) {
        let nextHeight: number | undefined = undefined;

        const g = groups[groupIndex];
        const nextGroup = groups[groupIndex + 1];
        if (nextGroup) {
            while (i < nextGroup.index) {
                nextHeight = (nextHeight ?? 0) + cellMeasurer.rowHeight({index: i});
                i++;
            }
        }

        divs.push(<div
            key={g.value.key || '__null'}
            style={{height: nextHeight ?? '100vh'}}
        >
            <GroupDivider
                groupValue={g.value}
                top={0}
            />
        </div>);

        if (groupIndex === groups.length - 1) {
            break;
        }
        groupIndex++;
    }

    return <div style={{
        position: 'absolute',
        top: 0,
        left: 0,
        right: 0,
        pointerEvents: 'none',
    }}>
        <div
            style={{
                position: 'relative',
                overflow: 'hidden',
                height,
            }}
            ref={ref}
        >
            {divs}
        </div>
    </div>
});
