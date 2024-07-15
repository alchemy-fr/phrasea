import React from 'react';
import {AssetOrAssetContainer} from '../../../../types';
import assetClasses from '../../classes';
import {PrivacyTooltip} from '../../../Ui/PrivacyChip';
import IconButton from '@mui/material/IconButton';
import ShoppingCartIcon from '@mui/icons-material/ShoppingCart';
import SettingsIcon from '@mui/icons-material/Settings';
import AssetThumb from '../../../Media/Asset/AssetThumb';
import {replaceHighlight} from '../../../Media/Asset/Attribute/AttributeHighlights';
import AssetTagList from '../../../Media/Asset/Widgets/AssetTagList';
import AssetCollectionList from '../../../Media/Asset/Widgets/AssetCollectionList';
import {AssetItemProps, ItemOverlayRenderer, OnPreviewToggle} from '../../types';
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
    onToggle,
    onContextMenuOpen,
    onPreviewToggle,
    onAddToBasket,
    itemComponent,
    itemOverlay,
}: Props<Item>) {
    const disabled = !asset.workspace;

    return (
        <AssetItemWrapper
            item={item}
            itemComponent={itemComponent}
            onToggle={onToggle}
            selected={selected}
        >
            <div className={assetClasses.controls}>
                <Checkbox
                    className={assetClasses.checkBtb}
                    checked={selected}
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
                    <PrivacyTooltip
                        iconProps={{
                            fontSize: 'small',
                        }}
                        privacy={asset.privacy}
                        noAccess={disabled}
                    />
                    {!disabled ? (
                        <>
                            {onAddToBasket ? (
                                <IconButton
                                    className={assetClasses.cartBtn}
                                    onMouseDown={stopPropagation}
                                    onDoubleClick={stopPropagation}
                                    onClick={e => onAddToBasket(asset, e)}
                                >
                                    <ShoppingCartIcon fontSize={'small'} />
                                </IconButton>
                            ) : null}
                            {onContextMenuOpen && (
                                <IconButton
                                    className={assetClasses.settingBtn}
                                    onMouseDown={stopPropagation}
                                    onDoubleClick={stopPropagation}
                                    onClick={function (e) {
                                        onContextMenuOpen(
                                            e,
                                            item,
                                            e.currentTarget
                                        );
                                    }}
                                >
                                    <SettingsIcon fontSize={'small'} />
                                </IconButton>
                            )}
                        </>
                    ) : (
                        ''
                    )}
                </div>
            </div>
            <AssetThumb
                asset={asset}
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
            />
            <div className={assetClasses.legend}>
                <div className={assetClasses.title}>
                    {asset.titleHighlight
                        ? replaceHighlight(asset.titleHighlight)
                        : asset.resolvedTitle ?? asset.title}
                </div>
                {asset.tags && asset.tags.length > 0 && (
                    <div>
                        <AssetTagList tags={asset.tags!} />
                    </div>
                )}
                {asset.collections && asset.collections.length > 0 && (
                    <div>
                        <AssetCollectionList collections={asset.collections!} />
                    </div>
                )}
            </div>
            {itemOverlay ? itemOverlay({
                item
            }) : ''}
        </AssetItemWrapper>
    );
}
