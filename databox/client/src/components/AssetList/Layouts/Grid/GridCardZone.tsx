import React, {useMemo} from 'react';
import {Box, Chip, CSSObject, SxProps, Theme} from '@mui/material';
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

// anchor -> absolute position over the thumb. Each anchor is content-sized
// (flexbox) and absolutely positioned, so items can overflow their cell instead
// of being clamped to a 3x3 grid track.
const overPos: Record<string, CSSObject> = {
    tl: {top: 0, left: 0, alignItems: 'flex-start'},
    tc: {
        top: 0,
        left: '50%',
        transform: 'translateX(-50%)',
        alignItems: 'center',
    },
    tr: {top: 0, right: 0, alignItems: 'flex-end'},
    ml: {
        top: '50%',
        left: 0,
        transform: 'translateY(-50%)',
        alignItems: 'flex-start',
    },
    cc: {
        top: '50%',
        left: '50%',
        transform: 'translate(-50%, -50%)',
        alignItems: 'center',
    },
    mr: {
        top: '50%',
        right: 0,
        transform: 'translateY(-50%)',
        alignItems: 'flex-end',
    },
    bl: {bottom: 0, left: 0, alignItems: 'flex-start'},
    bc: {
        bottom: 0,
        left: '50%',
        transform: 'translateX(-50%)',
        alignItems: 'center',
    },
    br: {bottom: 0, right: 0, alignItems: 'flex-end'},
};

const bandAlign: Record<string, string> = {
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
                        ? ([
                              {
                                  position: 'absolute',
                                  width: 'max-content',
                                  maxWidth: '100%',
                                  overflow: 'visible',
                              },
                              overPos[anchor],
                          ] as SxProps<Theme>)
                        : {
                              gridColumn:
                                  anchor === 'l' ? 1 : anchor === 'c' ? 2 : 3,
                              alignItems: bandAlign[anchor],
                              overflow: 'hidden',
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
        // Overlay covering the whole thumbnail; positioning context for the
        // absolutely-anchored cells. `display: block` (not contents) so the
        // absolute children resolve against this padded box.
        // Scoped under .thumbWrapper to beat its `> div { display: contents }`.
        [`.${assetClasses.thumbWrapper} > .${gridZoneClasses.over}`]: {
            position: 'absolute',
            inset: 0,
            zIndex: 2,
            display: 'block',
            pointerEvents: 'none',
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
            padding: theme.spacing(0.5),
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
