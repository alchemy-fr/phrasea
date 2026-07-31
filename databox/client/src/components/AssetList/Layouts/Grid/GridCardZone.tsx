import React, {useMemo} from 'react';
import {Box, Chip, CSSObject, SxProps, Theme} from '@mui/material';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import CancelIcon from '@mui/icons-material/Cancel';
import {Asset, GridAnchor, GridRegion, ProfileItem} from '../../../../types';
import {AttributeType} from '../../../../api/types.ts';
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

// below band anchors: absolutely positioned like `over` (top-aligned row) so a
// value can overflow its 1/3 cell instead of being clamped to a grid track.
const belowPos: Record<string, CSSObject> = {
    l: {top: 0, left: 0, alignItems: 'flex-start'},
    c: {
        top: 0,
        left: '50%',
        transform: 'translateX(-50%)',
        alignItems: 'center',
    },
    r: {top: 0, right: 0, alignItems: 'flex-end'},
};

// Approximate height reserved per stacked row in a band (chip + gap).
const BAND_ROW_HEIGHT = 28;

type EntityRaw = {emoji?: string; color?: string};

function GridItemValue({resolved}: {resolved: ResolvedGridItem}) {
    const {item, definition, value, nodes, raws, richCapable} = resolved;
    const type = definition.type;
    const showLabel = item.showLabel ?? false;
    const label = definition.displayName ?? definition.name;

    const labelPrefix = showLabel ? (
        <Box component="span" className={gridZoneClasses.label}>
            {label}:
        </Box>
    ) : null;

    // Boolean type: optional true/false icon instead of text.
    if (type === AttributeType.Boolean && item.booleanIcon) {
        const bools = raws.filter(r => typeof r === 'boolean') as boolean[];
        return (
            <Box className={gridZoneClasses.rich} title={value || undefined}>
                {labelPrefix}
                {bools.length > 0
                    ? bools.map((b, i) =>
                          b ? (
                              <CheckCircleIcon
                                  key={i}
                                  color="success"
                                  fontSize="small"
                              />
                          ) : (
                              <CancelIcon
                                  key={i}
                                  color="error"
                                  fontSize="small"
                              />
                          )
                      )
                    : '—'}
            </Box>
        );
    }

    // AttributeEntity type: optionally show only the emoji or only the color.
    if (
        type === AttributeType.AttributeEntity &&
        item.entityDisplay &&
        item.entityDisplay !== 'full'
    ) {
        const entities = raws as (EntityRaw | undefined)[];
        return (
            <Box className={gridZoneClasses.rich} title={value || undefined}>
                {labelPrefix}
                {entities.map((e, i) =>
                    item.entityDisplay === 'emoji' ? (
                        <Box component="span" key={i}>
                            {e?.emoji ?? '—'}
                        </Box>
                    ) : (
                        <Box
                            key={i}
                            className={gridZoneClasses.swatch}
                            sx={{backgroundColor: e?.color || 'transparent'}}
                        />
                    )
                )}
            </Box>
        );
    }

    const variant = item.variant ?? (richCapable ? 'rich' : 'chip');

    // Rich: render the type formatter's ReactNode(s) (tag pills, entity chips…).
    if (variant === 'rich') {
        return (
            <Box className={gridZoneClasses.rich} title={value || undefined}>
                {labelPrefix}
                {nodes.length > 0
                    ? nodes.map((n, i) => (
                          <React.Fragment key={i}>{n}</React.Fragment>
                      ))
                    : '—'}
            </Box>
        );
    }

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

    // 'chip': a compact chip wrapping the plain-text value.
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

    const isOver = region === 'over';
    const anchors = isOver ? OVER_ANCHORS : BAND_ANCHORS;
    // Reserve vertical room for the tallest anchor stack so the absolutely
    // positioned band cells stay visible.
    const bandRows = isOver
        ? 0
        : Math.max(0, ...[...byAnchor.values()].map(a => a.length));

    return (
        <Box
            className={isOver ? gridZoneClasses.over : gridZoneClasses.band}
            sx={isOver ? undefined : {minHeight: bandRows * BAND_ROW_HEIGHT}}
        >
            {anchors.map(anchor => {
                const cellItems = byAnchor.get(anchor);
                if (!cellItems || cellItems.length === 0) {
                    return null;
                }

                const cellSx = [
                    {
                        position: 'absolute',
                        width: 'max-content',
                        maxWidth: '100%',
                        overflow: 'visible',
                    },
                    isOver ? overPos[anchor] : belowPos[anchor],
                ] as SxProps<Theme>;

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
    rich: 'gcz-rich',
    label: 'gcz-label',
    swatch: 'gcz-swatch',
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
            zIndex: 1,
            display: 'block',
            pointerEvents: 'none',
        },
        // below band: positioning context for absolutely-anchored cells; values
        // may overflow their 1/3 cell but are clipped to the card width.
        [`.${gridZoneClasses.band}`]: {
            position: 'relative',
            overflow: 'hidden',
            width: '100%',
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
        // Rich values (tag pills, etc.): let the type formatter's nodes flow.
        [`.${gridZoneClasses.rich}`]: {
            display: 'flex',
            flexWrap: 'wrap',
            alignItems: 'center',
            gap: theme.spacing(0.25),
            maxWidth: '100%',
            minWidth: 0,
            fontSize: 12,
            pointerEvents: 'auto',
        },
        [`.${gridZoneClasses.label}`]: {
            fontSize: 12,
            opacity: 0.7,
            whiteSpace: 'nowrap',
        },
        [`.${gridZoneClasses.swatch}`]: {
            width: 14,
            height: 14,
            borderRadius: '50%',
            border: '1px solid rgba(0,0,0,0.25)',
            flex: '0 0 auto',
        },
    };
}
