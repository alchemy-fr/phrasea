// @ts-nocheck
import React, {MouseEvent, useContext} from 'react';
import {
    OnPreviewToggle,
    OnSelectAsset,
    OnUnselectAsset,
    TOnContextMenuOpen,
} from './Layout';
import {Box, Checkbox, Grid, IconButton} from '@mui/material';
import AssetThumb, {createThumbActiveStyle} from '../../Asset/AssetThumb';
import {DisplayContext} from '../../DisplayContext';
import {Asset} from '../../../../types';
import SettingsIcon from '@mui/icons-material/Settings';
import Attributes, {replaceHighlight} from '../../Asset/Attribute/Attributes';
import assetClasses from './classes';
import AssetTagList from '../../Asset/Widgets/AssetTagList';
import AssetCollectionList from '../../Asset/Widgets/AssetCollectionList';
import {stopPropagation} from '../../../../lib/stdFuncs';
import PrivacyChip from '../../../Ui/PrivacyChip';
import GroupRow from '../../../AssetList/Layouts/GroupRow.tsx';

const AssetItem = React.memo(
    ({
        asset,
        selected,
        onSelect,
        onUnselect,
        onContextMenuOpen,
        thumbSize,
        displayAttributes,
        onPreviewToggle,
    }: {
        asset: Asset;
        onSelect: OnSelectAsset;
        onUnselect: OnUnselectAsset;
        displayAttributes: boolean;
        selected: boolean;
        onContextMenuOpen?: TOnContextMenuOpen;
        thumbSize: number;
        onPreviewToggle?: OnPreviewToggle;
    }) => {
        return (
            <div
                onMouseDown={e => onSelect(asset.id, e)}
                className={`${assetClasses.item} ${selected ? 'selected' : ''}`}
            >
                <Checkbox
                    className={assetClasses.checkBtb}
                    checked={selected}
                    color={'primary'}
                    onMouseDown={stopPropagation}
                    onChange={e =>
                        (e.target.checked ? onSelect : onUnselect)(asset.id, {
                            ctrlKey: true,
                            preventDefault() {},
                        } as MouseEvent)
                    }
                />
                <Grid container spacing={2} wrap={'nowrap'}>
                    <Grid item>
                        {onContextMenuOpen && (
                            <IconButton
                                className={assetClasses.settingBtn}
                                onClick={e => onContextMenuOpen(e, asset)}
                                color={'inherit'}
                            >
                                <SettingsIcon
                                    color={'inherit'}
                                    fontSize={'small'}
                                    scale={0.45}
                                />
                            </IconButton>
                        )}
                        <AssetThumb
                            onMouseOver={
                                onPreviewToggle
                                    ? e =>
                                          onPreviewToggle(
                                              asset,
                                              true,
                                              e.currentTarget as HTMLElement
                                          )
                                    : undefined
                            }
                            onMouseLeave={
                                onPreviewToggle
                                    ? e =>
                                          onPreviewToggle(
                                              asset,
                                              false,
                                              e.currentTarget as HTMLElement
                                          )
                                    : undefined
                            }
                            asset={asset}
                            thumbSize={thumbSize}
                            selected={selected}
                        />
                    </Grid>
                    <Grid item className={assetClasses.attributes}>
                        <div className={assetClasses.title}>
                            {asset.titleHighlight
                                ? replaceHighlight(asset.titleHighlight)
                                : asset.resolvedTitle ?? asset.title}
                        </div>
                        {asset.tags.length > 0 && (
                            <div>
                                <AssetTagList tags={asset.tags} />
                            </div>
                        )}
                        <PrivacyChip privacy={asset.privacy} size={'small'} />
                        <div>
                            <AssetCollectionList
                                workspace={asset.workspace}
                                collections={asset.collections}
                            />
                        </div>
                        {displayAttributes && (
                            <Attributes asset={asset} controls={true} />
                        )}
                    </Grid>
                </Grid>
            </div>
        );
    }
);

export default function ListLayout({
    assets,
    onSelect,
    onUnselect,
    onContextMenuOpen,
    selectedAssets,
    onPreviewToggle,
    onOpen,
    searchMenuHeight,
}: LayoutProps) {
    const {thumbSize, displayAttributes} = useContext(DisplayContext)!;

    return (
        <Box
            sx={theme => ({
                [`.${assetClasses.item}`]: {
                    'p': 2,
                    'position': 'relative',
                    [`.${assetClasses.checkBtb}, .${assetClasses.settingBtn}`]:
                        {
                            position: 'absolute',
                            zIndex: 2,
                            opacity: 0,
                            transform: `translateY(-10px)`,
                            transition: theme.transitions.create(
                                ['opacity', 'transform'],
                                {duration: 300}
                            ),
                        },
                    [`.${assetClasses.checkBtb}`]: {
                        transform: `translateX(-10px)`,
                        left: 15,
                        top: 15,
                    },
                    [`.${assetClasses.settingBtn}`]: {
                        position: 'absolute',
                        right: 1,
                        top: 1,
                    },
                    '&:hover, &.selected': {
                        [`.${assetClasses.checkBtb}, .${assetClasses.settingBtn}`]:
                            {
                                opacity: 1,
                                transform: `translateY(0)`,
                            },
                    },
                    '&.selected': {
                        boxShadow: theme.shadows[2],
                    },
                    [`.${assetClasses.thumbWrapper}`]: createThumbActiveStyle(),
                },
            })}
        >
            {assets.map(a => {
                const contextMenu = onContextMenuOpen;

                return (
                    <GroupRow
                        key={a.id}
                        asset={a}
                        searchMenuHeight={searchMenuHeight}
                    >
                        <div
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
                                selected={selectedAssets.includes(a.id)}
                                onContextMenuOpen={
                                    contextMenu ? onContextMenuOpen : undefined
                                }
                                displayAttributes={displayAttributes}
                                onSelect={onSelect}
                                onUnselect={onUnselect}
                                thumbSize={thumbSize}
                                onPreviewToggle={onPreviewToggle}
                            />
                        </div>
                    </GroupRow>
                );
            })}
        </Box>
    );
}
