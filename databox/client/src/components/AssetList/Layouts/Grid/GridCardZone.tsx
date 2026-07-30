import React, {useMemo} from 'react';
import {Box, Chip, SxProps, Theme} from '@mui/material';
import {Asset, GridAnchor, GridRegion, ProfileItem} from '../../../../types';
import assetClasses from '../../classes';
import {ResolvedGridItem, useResolvedGridItems} from './resolveGridItem.ts';

type Props = {
    asset: Asset;
    items: ProfileItem[];
    region: GridRegion;
};

const OVER_ANCHORS: GridAnchor[] = [
    'tl',
    'tc',
    'tr',
    'ml',
    'cc',
    'mr',
    'bl',
    'bc',
    'br',
];
const BAND_ANCHORS: GridAnchor[] = ['l', 'c', 'r'];

// anchor -> CSS grid cell + inner alignment (over = 3x3 grid over the thumb).
const overCell: Record<
    string,
    {row: number; col: number; align: string; justify: string}
> = {
    tl: {row: 1, col: 1, align: 'flex-start', justify: 'flex-start'},
    tc: {row: 1, col: 2, align: 'flex-start', justify: 'center'},
    tr: {row: 1, col: 3, align: 'flex-start', justify: 'flex-end'},
    ml: {row: 2, col: 1, align: 'center', justify: 'flex-start'},
    cc: {row: 2, col: 2, align: 'center', justify: 'center'},
    mr: {row: 2, col: 3, align: 'center', justify: 'flex-end'},
    bl: {row: 3, col: 1, align: 'flex-end', justify: 'flex-start'},
    bc: {row: 3, col: 2, align: 'flex-end', justify: 'center'},
    br: {row: 3, col: 3, align: 'flex-end', justify: 'flex-end'},
};

const bandJustify: Record<string, string> = {
    l: 'flex-start',
    c: 'center',
    r: 'flex-end',
};

function GridItemValue({resolved}: {resolved: ResolvedGridItem}) {
    const {item, definition, value} = resolved;
    const variant = item.variant ?? 'chip';
    const showLabel = item.showLabel ?? false;
    const label = definition.displayName ?? definition.name;

    const text = showLabel
        ? value
            ? `${label}: ${value}`
            : label
        : value || '—';

    if (variant === 'text') {
        return (
            <Box component="span" className={gridZoneClasses.text} title={text}>
                {text}
            </Box>
        );
    }

    // 'chip' (and 'icon' until dedicated icons land) render as a compact chip.
    return (
        <Chip
            className={gridZoneClasses.chip}
            size="small"
            label={text}
            title={text}
        />
    );
}

function GridCardZone({asset, items, region}: Props) {
    const regionItems = useMemo(
        () => items.filter(i => i.placement?.region === region),
        [items, region]
    );
    const resolved = useResolvedGridItems(asset, regionItems);

    const byAnchor = useMemo(() => {
        const map = new Map<GridAnchor, ResolvedGridItem[]>();
        for (const r of resolved) {
            const anchor = r.item.placement!.anchor;
            const arr = map.get(anchor) ?? [];
            arr.push(r);
            map.set(anchor, arr);
        }
        for (const arr of map.values()) {
            arr.sort(
                (a, b) =>
                    (a.item.placement!.order ?? 0) -
                    (b.item.placement!.order ?? 0)
            );
        }
        return map;
    }, [resolved]);

    if (resolved.length === 0) {
        return null;
    }

    const anchors = region === 'over' ? OVER_ANCHORS : BAND_ANCHORS;

    return (
        <Box
            className={
                region === 'over' ? gridZoneClasses.over : gridZoneClasses.band
            }
        >
            {anchors.map(anchor => {
                const cellItems = byAnchor.get(anchor);
                if (!cellItems || cellItems.length === 0) {
                    return null;
                }

                const cellSx: SxProps<Theme> =
                    region === 'over'
                        ? {
                              gridRow: overCell[anchor].row,
                              gridColumn: overCell[anchor].col,
                              alignItems: overCell[anchor].align,
                              justifyContent: overCell[anchor].justify,
                          }
                        : {
                              gridColumn:
                                  anchor === 'l' ? 1 : anchor === 'c' ? 2 : 3,
                              justifyContent: bandJustify[anchor],
                          };

                return (
                    <Box
                        key={anchor}
                        className={gridZoneClasses.cell}
                        sx={cellSx}
                    >
                        {cellItems.map(r => (
                            <GridItemValue key={r.item.id} resolved={r} />
                        ))}
                    </Box>
                );
            })}
        </Box>
    );
}

export default React.memo(GridCardZone) as typeof GridCardZone;

export const gridZoneClasses = {
    over: 'gcz-over',
    band: 'gcz-band',
    cell: 'gcz-cell',
    chip: 'gcz-chip',
    text: 'gcz-text',
};

export function gridCardZoneSx(theme: Theme) {
    return {
        // Overlay covering the whole thumbnail; a 3x3 grid of anchor cells.
        // Scoped under .thumbWrapper to beat its `> div { display: contents }`.
        [`.${assetClasses.thumbWrapper} > .${gridZoneClasses.over}`]: {
            position: 'absolute',
            inset: 0,
            zIndex: 2,
            display: 'grid',
            gridTemplateColumns: 'repeat(3, 1fr)',
            gridTemplateRows: 'repeat(3, 1fr)',
            pointerEvents: 'none',
            padding: theme.spacing(0.5),
            gap: theme.spacing(0.5),
        },
        // above / below band: 3 columns in normal flow.
        [`.${gridZoneClasses.band}`]: {
            display: 'grid',
            gridTemplateColumns: 'repeat(3, 1fr)',
            alignItems: 'center',
            gap: theme.spacing(0.5),
            minWidth: 0,
        },
        [`.${gridZoneClasses.cell}`]: {
            display: 'flex',
            flexDirection: 'column',
            gap: theme.spacing(0.25),
            minWidth: 0,
            overflow: 'hidden',
        },
        [`.${gridZoneClasses.chip}`]: {
            maxWidth: '100%',
            pointerEvents: 'auto',
        },
        [`.${gridZoneClasses.text}`]: {
            fontSize: 12,
            lineHeight: 1.3,
            overflow: 'hidden',
            textOverflow: 'ellipsis',
            whiteSpace: 'nowrap',
            maxWidth: '100%',
        },
    };
}
