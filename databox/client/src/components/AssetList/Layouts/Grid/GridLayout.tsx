import React, {useContext} from 'react';
import {alpha, Grid2 as Grid, Theme} from '@mui/material';
import {LayoutProps} from '../../types';
import {AssetOrAssetContainer} from '../../../../types';
import {DisplayContext} from '../../../Media/DisplayContext';
import {sectionDividerClassname} from '../../SectionDivider';
import assetClasses from '../../classes';
import GridPage from './GridPage';
import PreviewPopover from '../../PreviewPopover';
import {usePreview} from '../../usePreview';
import {tagListSx} from '../../../Media/Asset/Widgets/AssetTagList';
import {collectionListSx} from '../../../Media/Asset/Widgets/AssetCollectionList';
import LoadMoreButton from '../../LoadMoreButton.tsx';
import {useScrollTopPages} from '../../useScrollTopPages.ts';
import {
    createSizeTransition,
    thumbSx,
} from '../../../Media/Asset/AssetThumb.tsx';
import {useProfileStore} from '../../../../store/profileStore.ts';
import {ProfileItemSection} from '../../../../types';
import {gridCardZoneSx} from './GridCardZone.tsx';

export default function GridLayout<Item extends AssetOrAssetContainer>({
    toolbarHeight,
    pages,
    onToggle,
    onContextMenuOpen,
    onAddToBasket,
    onOpen,
    itemComponent,
    loadMore,
    itemToAsset,
    previewZIndex,
    ...layoutProps
}: LayoutProps<Item>) {
    const lineHeight = 26;
    const collLineHeight = 32;
    const tagLineHeight = 32;
    const d = useContext(DisplayContext)!.state;
    const listRef = React.useRef<HTMLDivElement | null>(null);

    // Reserve vertical space for the profile's above/below grid bands so the
    // fixed item height stays consistent (the "over" overlay adds no height).
    const gridBandRows = useProfileStore(s => {
        const items = (s.current?.items ?? []).filter(
            i => i.section === ProfileItemSection.Grid
        );
        const maxStack = (region: 'above' | 'below') => {
            const counts: Record<string, number> = {};
            for (const i of items) {
                if (i.placement?.region !== region) continue;
                const a = i.placement.anchor;
                counts[a] = (counts[a] ?? 0) + 1;
            }
            const values = Object.values(counts);
            return values.length ? Math.max(...values) : 0;
        };
        return maxStack('above') + maxStack('below');
    });
    const bandLineHeight = 28;

    useScrollTopPages(
        listRef.current?.closest(`.${assetClasses.scrollable}`),
        pages
    );

    const gridSx = React.useCallback(
        (theme: Theme) => {
            const spacing = Number(theme.spacing(1).slice(0, -2));

            const nameHeight = d.displayName
                ? spacing * 1.8 + lineHeight * d.nameRows
                : 0;
            let totalHeight = d.thumbSize + nameHeight;
            if (d.displayCollections) {
                totalHeight += collLineHeight * d.collectionsLimit;
            }
            if (d.displayTags) {
                totalHeight += tagLineHeight * d.tagsLimit;
            }
            totalHeight += gridBandRows * bandLineHeight;

            return {
                ...tagListSx(),
                ...collectionListSx(),
                ...thumbSx(d.thumbSize, theme),
                ...gridCardZoneSx(theme),
                p: 2,
                backgroundColor: theme.palette.background.default,
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
                        'position': 'absolute',
                        'transform': `translateY(-10px)`,
                        'zIndex': 2,
                        'opacity': 0,
                        'left': 0,
                        'top': 0,
                        'right': 0,
                        'padding': '1px',
                        'transition': theme.transitions.create(
                            ['opacity', 'transform'],
                            {duration: 300}
                        ),
                        '> div': {
                            float: 'right',
                        },
                        'background':
                            'linear-gradient(to bottom, rgba(255,255,255,0.8) 0%, rgba(255,255,255,0.5) 50%, rgba(255,255,255,0) 100%)',

                        ...theme.applyStyles('dark', {
                            background:
                                'linear-gradient(to bottom, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0.5) 50%, rgba(0,0,0,0) 100%)',
                        }),
                    },
                    '&:hover, &.selected': {
                        [`.${assetClasses.controls}`]: {
                            opacity: 1,
                            transform: `translateY(0)`,
                        },
                    },
                    [`.${assetClasses.privacy}`]: {
                        display: 'inline-block',
                        verticalAlign: 'middle',
                        mt: 0.5,
                        mr: 1,
                    },
                    '&.selected': {
                        backgroundColor: alpha(theme.palette.primary.main, 0.8),
                        boxShadow: theme.shadows[2],
                        [`.${assetClasses.legend}`]: {
                            color: theme.palette.primary.contrastText,
                        },
                    },
                },
                [`.${assetClasses.animatedThumb}`]: {
                    display: 'none',
                },
                [`.${assetClasses.name}`]: {
                    fontSize: 14,
                    lineHeight: `${lineHeight}px`,
                    overflow: 'hidden',
                    textOverflow: 'ellipsis',
                    ...(d.nameRows > 1
                        ? {
                              'display': d.displayName ? '-webkit-box' : 'none',
                              '-webkit-line-clamp': `${d.nameRows}`,
                              '-webkit-box-orient': 'vertical',
                          }
                        : {
                              display: d.displayName ? 'block' : 'none',
                              whiteSpace: 'nowrap',
                          }),
                },
                [`.${assetClasses.legend}`]: {
                    p: 1,
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 1,
                },
            };
        },
        [d, gridBandRows]
    );

    const {previewAnchorEl, onPreviewToggle, onPreviewHide} = usePreview([
        pages,
    ]);

    return (
        <>
            <Grid container spacing={1} sx={gridSx} ref={listRef}>
                {pages.map((page, i) => (
                    <GridPage
                        key={i}
                        page={i + 1}
                        toolbarHeight={toolbarHeight}
                        items={page}
                        itemToAsset={itemToAsset}
                        itemComponent={itemComponent}
                        onToggle={onToggle}
                        onPreviewToggle={onPreviewToggle}
                        onContextMenuOpen={onContextMenuOpen}
                        onAddToBasket={onAddToBasket}
                        onOpen={onOpen}
                        {...layoutProps}
                    />
                ))}
            </Grid>

            {loadMore ? (
                <LoadMoreButton onClick={loadMore} pages={pages} />
            ) : (
                ''
            )}

            <PreviewPopover
                onHide={onPreviewHide}
                key={previewAnchorEl?.asset.id ?? 'none'}
                asset={previewAnchorEl?.asset}
                anchorEl={previewAnchorEl?.anchorEl}
                displayAttributes={true}
                zIndex={previewZIndex}
            />
        </>
    );
}
