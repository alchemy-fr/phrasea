import {useContext} from 'react';
import {alpha, Grid, useTheme} from '@mui/material';
import {LayoutProps} from "../../types.ts";
import {AssetOrAssetContainer} from "../../../../types.ts";
import {DisplayContext} from "../../../Media/DisplayContext.tsx";
import {sectionDividerClassname} from "../../../Media/Search/Layout/SectionDivider";
import assetClasses from "../../../Media/Search/Layout/classes";
import {createSizeTransition} from "../../../Media/Asset/Thumb";
import {createThumbActiveStyle} from "../../../Media/Asset/AssetThumb";
import GridPage from "./GridPage.tsx";

const lineHeight = 26;
const collLineHeight = 32;
const tagLineHeight = 32;

export default function GridLayout<Item extends AssetOrAssetContainer>({
    searchMenuHeight,
    pages,
    onToggle,
    onPreviewToggle,
    onContextMenuOpen,
    onAddToBasket,
    onOpen,
    selection,
}: LayoutProps<Item>) {
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
            {pages.map((page, i) => <GridPage
                key={i}
                page={i + 1}
                searchMenuHeight={searchMenuHeight}
                items={page}
                onToggle={onToggle}
                onPreviewToggle={onPreviewToggle}
                onContextMenuOpen={onContextMenuOpen}
                onAddToBasket={onAddToBasket}
                onOpen={onOpen}
                selection={selection}
            />)}
        </Grid>
    );
}
