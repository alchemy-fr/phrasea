import React, {useMemo} from 'react';
import {Box, Chip, CSSObject, Theme} from '@mui/material';
import {defaultChipColors} from '@alchemy/phrasea-framework';
import {
    Asset,
    GridAnchor,
    GridRegion,
    ProfileItem,
    ProfileItemSize,
    ProfileItemVariant,
} from '../../../../types';
import assetClasses from '../../classes';
import {ResolvedGridItem, useResolvedGridItems} from './resolveGridItem.ts';

type Props = {
    asset: Asset;
    items: ProfileItem[];
    region: GridRegion;
};

// 'reserved' slots hold the room of the built-in card elements (checkbox on
// the top-left, menu button on the top-right, file type chip on the
// bottom-right) so values never overlap them.
type GridSlot = GridAnchor | 'reserved';

const OVER_ROWS: GridSlot[][] = [
    ['reserved', 'tc', 'reserved'],
    ['ml', 'cc', 'mr'],
    ['bl', 'bc', 'reserved'],
];
const BAND_ROWS: GridSlot[][] = [['l', 'c', 'r']];

// Vertical alignment of the cells within each `over` row (top/middle/bottom).
const OVER_ROW_ALIGN = ['flex-start', 'center', 'flex-end'];

// Size of a reserved corner (checkbox / menu button hit area).
const CONTROLS_CELL_SIZE = 42;

// Horizontal alignment of the values stacked in a cell, from the anchor's
// last letter (l/c/r).
const cellAlign = (anchor: GridAnchor) =>
    anchor.endsWith('r')
        ? 'flex-end'
        : anchor.endsWith('c')
          ? 'center'
          : 'flex-start';

// Modifier classes (size + chip color) appended to each value's root node;
// the matching rules live in gridCardZoneSx.
function itemModifiers(item: ProfileItem): string {
    const parts = [gridZoneClasses.sizes[item.size ?? ProfileItemSize.Medium]];
    if (item.color) {
        parts.push(`${gridZoneClasses.colorPrefix}${item.color}`);
    }
    return parts.join(' ');
}

function GridItemValue({resolved}: {resolved: ResolvedGridItem}) {
    const {item, definition, value, nodes, richCapable} = resolved;
    const showLabel = item.showLabel ?? false;
    const label = definition.displayName ?? definition.name;
    const withMods = (base: string) => `${base} ${itemModifiers(item)}`;

    const labelPrefix = showLabel ? (
        <Box component="span" className={gridZoneClasses.label}>
            {label}:
        </Box>
    ) : null;

    const variant =
        item.variant ??
        (richCapable ? ProfileItemVariant.Rich : ProfileItemVariant.Chip);

    // Rich: render the type formatter's ReactNode(s) (tag pills, entity
    // chips…), shaped by the item's format (e.g. Privacy's "short" format,
    // AttributeEntity's "emoji"/"color" formats).
    if (variant === ProfileItemVariant.Rich) {
        return (
            <Box
                className={withMods(gridZoneClasses.rich)}
                title={value || undefined}
            >
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

    if (variant === ProfileItemVariant.Text) {
        return (
            <Box
                component="span"
                className={withMods(gridZoneClasses.text)}
                title={text}
            >
                {text}
            </Box>
        );
    }

    // 'chip': a compact chip wrapping the plain-text value.
    return (
        <Chip
            className={withMods(gridZoneClasses.chip)}
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
    const rows = isOver ? OVER_ROWS : BAND_ROWS;

    // Every row/cell is rendered (even empty) so that `space-between` keeps
    // left/center/right and top/middle/bottom anchored to their position.
    return (
        <Box className={isOver ? gridZoneClasses.over : gridZoneClasses.band}>
            {rows.map((anchors, rowIndex) => (
                <Box
                    key={rowIndex}
                    className={gridZoneClasses.row}
                    sx={{
                        alignItems: isOver
                            ? OVER_ROW_ALIGN[rowIndex]
                            : 'flex-start',
                    }}
                >
                    {anchors.map((slot, slotIndex) =>
                        slot === 'reserved' ? (
                            <Box
                                key={`reserved-${slotIndex}`}
                                className={gridZoneClasses.reserved}
                            />
                        ) : (
                            <Box
                                key={slot}
                                className={gridZoneClasses.cell}
                                sx={{alignItems: cellAlign(slot)}}
                            >
                                {(byAnchor.get(slot) ?? []).map(r => (
                                    <GridItemValue
                                        key={r.item.id}
                                        resolved={r}
                                    />
                                ))}
                            </Box>
                        )
                    )}
                </Box>
            ))}
        </Box>
    );
}

export default React.memo(GridCardZone) as typeof GridCardZone;

export const gridZoneClasses = {
    over: 'gcz-over',
    band: 'gcz-band',
    row: 'gcz-row',
    cell: 'gcz-cell',
    reserved: 'gcz-reserved',
    chip: 'gcz-chip',
    text: 'gcz-text',
    rich: 'gcz-rich',
    label: 'gcz-label',
    sizes: {
        small: 'gcz-sz-sm',
        medium: 'gcz-sz-md',
        large: 'gcz-sz-lg',
    } satisfies Record<ProfileItemSize, string>,
    colorPrefix: 'gcz-c-',
};

export function gridCardZoneSx(theme: Theme) {
    const {cell, sizes, colorPrefix} = gridZoneClasses;

    // One rule per theme chip color, targeting both a chip root carrying the
    // modifier (chip variant) and chips rendered inside a value (rich variant).
    const chipColors = {...defaultChipColors, ...(theme.palette.chips ?? {})};
    const colorRules: Record<string, CSSObject> = {};
    for (const [name, color] of Object.entries(chipColors)) {
        colorRules[
            [
                `.${cell} .${colorPrefix}${name} .MuiChip-root`,
                `.${cell} .MuiChip-root.${colorPrefix}${name}`,
            ].join(', ')
        ] = {
            'backgroundColor': color.main,
            'color': color.contrastText,
            '.MuiChip-icon': {
                color: 'inherit',
            },
        };
    }

    return {
        // Overlay covering the whole thumbnail: a column of 3 flex rows
        // (top/middle/bottom) spread with space-between.
        // Scoped under .thumbWrapper to beat its `> div { display: contents }`.
        [`.${assetClasses.thumbWrapper} > .${gridZoneClasses.over}`]: {
            position: 'absolute',
            inset: 0,
            zIndex: 1,
            display: 'flex',
            flexDirection: 'column',
            justifyContent: 'space-between',
            pointerEvents: 'none',
        },
        [`.${gridZoneClasses.band}`]: {
            width: '100%',
        },
        // A row of 3 cells (left/center/right): flexbox shares the width so
        // cells on the same row never overlap; they shrink instead and their
        // values are rendered with an ellipsis.
        [`.${gridZoneClasses.row}`]: {
            display: 'flex',
            justifyContent: 'space-between',
            minWidth: 0,
            maxWidth: '100%',
        },
        [`.${gridZoneClasses.cell}`]: {
            'display': 'flex',
            'flexDirection': 'column',
            'gap': theme.spacing(0.25),
            'minWidth': 0,
            'padding': theme.spacing(0.5),
            '&:empty': {
                padding: 0,
            },
        },
        // Top corners kept free for the item controls (checkbox, menu button).
        [`.${gridZoneClasses.reserved}`]: {
            flex: '0 0 auto',
            width: CONTROLS_CELL_SIZE,
            height: CONTROLS_CELL_SIZE,
        },
        // Chips over the image must be fully opaque (MUI's default chip
        // background is translucent) and flush with their cell.
        [`.${gridZoneClasses.cell} .MuiChip-root`]: {
            'marginLeft': 0,
            '.MuiChip-label:empty': {
                paddingLeft: 0,
            },
        },
        [`.${gridZoneClasses.cell} .MuiChip-filled.MuiChip-colorDefault`]: {
            backgroundColor: theme.palette.grey[300],
            color: theme.palette.text.primary,
            ...theme.applyStyles('dark', {
                backgroundColor: theme.palette.grey[700],
            }),
        },
        ...colorRules,
        // Value sizes: text and chips scale together (any variant).
        [`.${cell} .${sizes.small}`]: {
            fontSize: 11,
        },
        [`.${cell} .${sizes.large}`]: {
            fontSize: 14,
        },
        [`.${cell} .${sizes.small} .MuiChip-root, .${cell} .MuiChip-root.${sizes.small}`]:
            {
                height: 20,
                fontSize: 11,
            },
        [`.${cell} .${sizes.large} .MuiChip-root, .${cell} .MuiChip-root.${sizes.large}`]:
            {
                height: 32,
                fontSize: 13,
            },
        [`.${cell} .${sizes.small} .MuiSvgIcon-root`]: {
            fontSize: 16,
        },
        [`.${cell} .${sizes.medium} .MuiSvgIcon-root`]: {
            fontSize: 20,
        },
        [`.${cell} .${sizes.large} .MuiSvgIcon-root`]: {
            fontSize: 24,
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
    };
}
