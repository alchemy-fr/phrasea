import React, {ForwardedRef, RefObject} from 'react';
import {Asset, AssetOrAssetContainer, GroupValue} from '../../../types';
import {ItemToAssetFunc} from '../types.ts';
import GroupDivider from './GroupDivider.tsx';
import {CellMeasurerCache} from 'react-virtualized';
import PageDivider from "../PageDivider.tsx";
import {getPage} from "./page.ts";

type GroupSet = {
    index: number;
    value: GroupValue;
    key: string | null;
};

type Position = {
    page?: number;
    group?: GroupValue;
    height?: number;
}

type Props<Item extends AssetOrAssetContainer> = {
    pages: Item[][];
    itemToAsset: ItemToAssetFunc<Item> | undefined;
    cellMeasurer: CellMeasurerCache;
    height: number;
    hasGroups: boolean;
};

export default React.forwardRef<HTMLDivElement, Props<any>>(
    function VirtualizedGroups<Item extends AssetOrAssetContainer>(
        {pages, height, itemToAsset, cellMeasurer, hasGroups}: Props<Item>,
        ref: ForwardedRef<HTMLDivElement>
    ) {
        const [_inc, setInc] = React.useState(0);

        const {groups, rowCount} = React.useMemo(() => {
            const groups: GroupSet[] = [];
            const all = pages.flat();
            const rowCount = all.length;

            if (hasGroups) {
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
            }

            return {groups, rowCount};
        }, [pages, cellMeasurer]);

        React.useEffect(() => {
            const onScroll = () => {
                setInc(p => p + 1);
            };

            (ref as RefObject<HTMLDivElement>).current?.addEventListener(
                'scroll',
                onScroll
            );

            return () => {
                (ref as RefObject<HTMLDivElement>).current?.removeEventListener(
                    'scroll',
                    onScroll
                );
            };
        }, [ref]);

        const positions: Position[] = [{}];
        let groupCursor = 0;
        let currentGroup: GroupSet | undefined = groups[groupCursor];
        let currPos: Position = positions[0];

        const flush = () => {
            currPos.height ??= 0;
            currPos = {};
            positions.push(currPos);
        }

        let currentPage : number | undefined;
        for (let i = 0; i < rowCount; i++) {
            const {
                pageIndex,
                itemIndex,
            } = getPage(pages, i);

            const isNewGroup = currentGroup && i === currentGroup.index;
            if (isNewGroup) {
                flush();
                currPos.group = currentGroup.value;
                if (currentPage) {
                    currPos.page = currentPage;
                }
                ++groupCursor;
                currentGroup = groups[groupCursor];
            }

            if (pageIndex > 0 && itemIndex === 0) {
                if (!isNewGroup) {
                    flush();
                }

                currentPage = pageIndex + 1;
                currPos.page = currentPage;
            }

            currPos.height = (currPos.height ?? 0) + cellMeasurer.rowHeight({index: i});
        }
        if (positions.length > 0) {
            positions[positions.length - 1].height = undefined;
        }

        return (
            <div
                style={{
                    position: 'absolute',
                    top: 0,
                    left: 0,
                    right: 10,
                    pointerEvents: 'none',
                }}
            >
                <div
                    style={{
                        position: 'relative',
                        overflow: 'hidden',
                        height,
                    }}
                    ref={ref}
                >
                    {positions.map((p, index) => <div
                        key={index}
                        style={{
                            height: p.height ?? '100vh',
                        }}
                    >
                        {p.page && !p.group ? <PageDivider
                            page={p.page}
                            top={0}
                        /> : ''}
                        {p.group ? <GroupDivider
                            groupValue={p.group}
                            top={0}
                            page={p.page}
                        /> : ''}
                    </div>)}
                </div>
            </div>
        );
    }
);
