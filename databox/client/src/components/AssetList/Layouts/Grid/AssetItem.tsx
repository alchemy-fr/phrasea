import React from 'react';
import {AssetOrAssetContainer, ProfileItemSection} from '../../../../types';
import assetClasses from '../../classes';
import {useProfileStore} from '../../../../store/profileStore.ts';
import GridCardZone from './GridCardZone.tsx';
import IconButton from '@mui/material/IconButton';
import MoreVertIcon from '@mui/icons-material/MoreVert';
import AssetThumb from '../../../Media/Asset/AssetThumb';
import {replaceHighlight} from '../../../Media/Asset/Attribute/AttributeHighlights';
import AssetTagList from '../../../Media/Asset/Widgets/AssetTagList';
import AssetCollectionList from '../../../Media/Asset/Widgets/AssetCollectionList';
import {
    AssetItemProps,
    ItemOverlayRenderer,
    OnPreviewToggle,
} from '../../types';
import {Checkbox} from '@mui/material';
import {stopPropagation} from '../../../../lib/stdFuncs';
import AssetItemWrapper from '../AssetItemWrapper';

type Props<Item extends AssetOrAssetContainer> = {
    onPreviewToggle?: OnPreviewToggle;
    itemOverlay?: ItemOverlayRenderer<Item>;
} & AssetItemProps<Item>;

export default function AssetItem<Item extends AssetOrAssetContainer>({
    item,
    asset,
    selected,
    disabled,
    onToggle,
    onContextMenuOpen,
    onPreviewToggle,
    itemComponent,
    itemOverlay,
    onOpen,
}: Props<Item>) {
    const profileItems = useProfileStore(s => s.current?.items);
    const gridItems = React.useMemo(
        () =>
            (profileItems ?? []).filter(
                i => i.section === ProfileItemSection.Grid
            ),
        [profileItems]
    );
    const hasGrid = gridItems.length > 0;

    return (
        <AssetItemWrapper
            item={item}
            itemComponent={itemComponent}
            onToggle={onToggle}
            selected={selected}
            disabled={disabled}
        >
            <div className={assetClasses.controls}>
                <Checkbox
                    className={assetClasses.checkBtb}
                    checked={selected}
                    disabled={disabled}
                    color={'primary'}
                    onMouseDown={stopPropagation}
                    onChange={() =>
                        onToggle(item, {
                            ctrlKey: true,
                            preventDefault() {},
                        } as React.MouseEvent)
                    }
                />
                <div>
                    {!disabled && onContextMenuOpen ? (
                        <IconButton
                            className={assetClasses.settingBtn}
                            onMouseDown={stopPropagation}
                            onDoubleClick={stopPropagation}
                            onClick={function (e) {
                                onContextMenuOpen(e, item, e.currentTarget);
                            }}
                        >
                            <MoreVertIcon fontSize={'small'} />
                        </IconButton>
                    ) : (
                        ''
                    )}
                </div>
            </div>
            <AssetThumb
                asset={asset}
                onPreviewToggle={onPreviewToggle}
                hideTypeChip={hasGrid}
                overlay={
                    hasGrid ? (
                        <GridCardZone
                            asset={asset}
                            items={gridItems}
                            region="over"
                        />
                    ) : undefined
                }
            />
            <div className={assetClasses.legend}>
                {!hasGrid && (
                    <>
                        <div className={assetClasses.name}>
                            {asset.nameHighlight
                                ? replaceHighlight(asset.nameHighlight)
                                : asset.name}
                        </div>
                        {asset.tags && asset.tags.length > 0 && (
                            <AssetTagList tags={asset.tags!} />
                        )}
                        {asset.collections && asset.collections.length > 0 && (
                            <AssetCollectionList
                                asset={asset}
                                onOpenAsset={onOpen}
                                collections={asset.collections!}
                            />
                        )}
                    </>
                )}
                {hasGrid && (
                    <GridCardZone
                        asset={asset}
                        items={gridItems}
                        region="below"
                    />
                )}
            </div>
            {itemOverlay
                ? itemOverlay({
                      item,
                  })
                : ''}
        </AssetItemWrapper>
    );
}
