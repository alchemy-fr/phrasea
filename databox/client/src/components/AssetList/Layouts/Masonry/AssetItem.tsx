import React from 'react';
import {AssetOrAssetContainer} from '../../../../types';
import assetClasses from '../../classes';
import IconButton from '@mui/material/IconButton';
import ShoppingCartIcon from '@mui/icons-material/ShoppingCart';
import SettingsIcon from '@mui/icons-material/Settings';
import AssetThumb from '../../../Media/Asset/AssetThumb';
import {AssetItemProps, OnPreviewToggle} from '../../types';
import {Checkbox} from '@mui/material';
import {stopPropagation} from '../../../../lib/stdFuncs';
import AssetItemWrapper from '../AssetItemWrapper';

type Props<Item extends AssetOrAssetContainer> = {
    onPreviewToggle?: OnPreviewToggle;
} & AssetItemProps<Item>;

export default function AssetItem<Item extends AssetOrAssetContainer>({
    item,
    asset,
    selected,
    disabled,
    onToggle,
    onContextMenuOpen,
    onPreviewToggle,
    onAddToBasket,
    itemComponent,
}: Props<Item>) {
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
                                    onContextMenuOpen(e, item, e.currentTarget);
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
            <AssetThumb asset={asset} onPreviewToggle={onPreviewToggle} />
        </AssetItemWrapper>
    );
}
