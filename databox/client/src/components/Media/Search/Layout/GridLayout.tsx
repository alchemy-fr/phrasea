// @ts-nocheck
import {useContext} from 'react';
import {alpha, Grid, useTheme} from '@mui/material';
import {LayoutProps,} from './Layout';
import {createThumbActiveStyle} from '../../Asset/AssetThumb';
import {DisplayContext} from '../../DisplayContext';
import {createSizeTransition} from '../../Asset/Thumb';
import assetClasses from '../../../AssetList/classes.ts';
import GroupRow from '../../../AssetList/Layouts/GroupRow.tsx';
import {sectionDividerClassname} from './SectionDivider';
import {AssetItem} from "./Grid/AssetItem.tsx";

const lineHeight = 26;
const collLineHeight = 32;
const tagLineHeight = 32;

export default function GridLayout({
    searchMenuHeight,
    assets,
    selectedAssets,
    onSelect,
    onUnselect,
    onPreviewToggle,
    onContextMenuOpen,
    onAddToBasket,
    onOpen,
}: LayoutProps) {
    const theme = useTheme();
    const d = useContext(DisplayContext)!;
    const spacing = Number(theme.spacing(1).slice(0, -2));

    const titleHeight = d.displayTitle
        ? spacing * 1.8 + lineHeight * d.titleRows
        : 0;
    let totalHeight = d.thumbSize + titleHeight;
    if (d.displayCollections) {
        totalHeight += collLineHeight * d.collectionsLimit;
    }
    if (d.displayTags) {
        totalHeight += tagLineHeight * d.tagsLimit;
    }

    return (
        <Grid
            container
            spacing={1}
            sx={theme => ({
                p: 2,
                [`.${sectionDividerClassname}`]: {
                    margin: `0 -${theme.spacing(1)}`,
                    width: `calc(100% + ${theme.spacing(2)})`,
                },
                [`.${assetClasses.item}`]: {
                    'width': d.thumbSize,
                    'height': totalHeight,
                    'transition': createSizeTransition(theme),
                    'position': 'relative',
                    [`.${assetClasses.controls}`]: {
                        position: 'absolute',
                        transform: `translateY(-10px)`,
                        zIndex: 2,
                        opacity: 0,
                        left: 0,
                        top: 0,
                        right: 0,
                        padding: '1px',
                        transition: theme.transitions.create(
                            ['opacity', 'transform'],
                            {duration: 300}
                        ),
                        background:
                            'linear-gradient(to bottom, rgba(255,255,255,0.8) 0%, rgba(255,255,255,0.5) 50%, rgba(255,255,255,0) 100%)',
                    },
                    [`.${assetClasses.settingBtn}`]: {
                        position: 'absolute',
                        right: 1,
                        top: 5,
                    },
                    [`.${assetClasses.cartBtn}`]: {
                        position: 'absolute',
                        right: 40,
                        top: 5,
                    },
                    ...createThumbActiveStyle(),
                    '&:hover, &.selected': {
                        [`.${assetClasses.controls}`]: {
                            opacity: 1,
                            transform: `translateY(0)`,
                        },
                    },
                    '&.selected': {
                        backgroundColor: alpha(theme.palette.primary.main, 0.8),
                        boxShadow: theme.shadows[2],
                        [`.${assetClasses.legend}`]: {
                            color: theme.palette.primary.contrastText,
                        },
                    },
                },
                [`.${assetClasses.thumbActive}`]: {
                    display: 'none',
                },
                [`.${assetClasses.title}`]: {
                    fontSize: 14,
                    p: 1,
                    height: titleHeight,
                    lineHeight: `${lineHeight}px`,
                    overflow: 'hidden',
                    textOverflow: 'ellipsis',
                    ...(d.titleRows > 1
                        ? {
                            'display': d.displayTitle
                                ? '-webkit-box'
                                : 'none',
                            '-webkit-line-clamp': `${d.titleRows}`,
                            '-webkit-box-orient': 'vertical',
                        }
                        : {
                            display: d.displayTitle ? 'block' : 'none',
                            whiteSpace: 'nowrap',
                        }),
                },
            })}
        >
            {assets.map(a => {
                const contextMenu = onContextMenuOpen;

                return (
                    <GroupRow
                        key={a.id}
                        asset={a}
                        toolbarHeight={searchMenuHeight}
                    >
                        <Grid
                            item
                            key={a.id}
                            onDoubleClick={
                                onOpen && a.original
                                    ? () => onOpen(a.id, a.original!.id)
                                    : undefined
                            }
                            onContextMenu={
                                onContextMenuOpen
                                    ? e => {
                                        if (!contextMenu) {
                                            e.preventDefault();
                                            return;
                                        }
                                        onContextMenuOpen!(e, a);
                                    }
                                    : undefined
                            }
                        >
                            <AssetItem
                                asset={a}
                                onAddToBasket={onAddToBasket}
                                selected={selectedAssets.includes(a.id)}
                                onContextMenuOpen={
                                    contextMenu ? onContextMenuOpen : undefined
                                }
                                onSelect={onSelect}
                                onPreviewToggle={onPreviewToggle}
                                onUnselect={onUnselect}
                                thumbSize={d.thumbSize}
                            />
                        </Grid>
                    </GroupRow>
                );
            })}
        </Grid>
    );
}
