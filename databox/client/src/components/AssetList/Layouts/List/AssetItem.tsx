import {MouseEvent} from 'react';
import {AssetOrAssetContainer} from '../../../../types';
import assetClasses from '../../classes';
import IconButton from '@mui/material/IconButton';
import ShoppingCartIcon from '@mui/icons-material/ShoppingCart';
import SettingsIcon from '@mui/icons-material/Settings';
import AssetThumb from '../../../Media/Asset/AssetThumb';
import {replaceHighlight} from '../../../Media/Asset/Attribute/AttributeHighlights.tsx';
import Attributes from '../../../Media/Asset/Attribute/Attributes';
import {AssetItemProps, OnPreviewToggle} from '../../types';
import {Checkbox} from '@mui/material';
import {stopPropagation} from '../../../../lib/stdFuncs';
import AssetItemWrapper from '../AssetItemWrapper';

type Props<Item extends AssetOrAssetContainer> = {
    onPreviewToggle?: OnPreviewToggle;
    displayAttributes: boolean;
} & AssetItemProps<Item>;

export default function AssetItem<Item extends AssetOrAssetContainer>({
    item,
    asset,
    selected,
    onToggle,
    onContextMenuOpen,
    onPreviewToggle,
    displayAttributes,
    onAddToBasket,
    itemComponent,
}: Props<Item>) {
    const disabled = !asset.workspace;

    return (
        <AssetItemWrapper
            item={item}
            itemComponent={itemComponent}
            onToggle={onToggle}
            selected={selected}
        >
            <div>
                <Checkbox
                    className={assetClasses.checkBtb}
                    checked={selected}
                    color={'primary'}
                    onMouseDown={stopPropagation}
                    onChange={() =>
                        onToggle(item, {
                            ctrlKey: true,
                            preventDefault() {},
                        } as MouseEvent)
                    }
                />
                {!disabled ? (
                    <div className={assetClasses.controls}>
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
                                onClick={e => onContextMenuOpen(e, item)}
                                color={'inherit'}
                            >
                                <SettingsIcon
                                    color={'inherit'}
                                    fontSize={'small'}
                                    scale={0.45}
                                />
                            </IconButton>
                        )}
                    </div>
                ) : (
                    ''
                )}
                <AssetThumb onPreviewToggle={onPreviewToggle} asset={asset} />
            </div>
            <div className={assetClasses.attributes}>
                <div className={assetClasses.title}>
                    {asset.titleHighlight
                        ? replaceHighlight(asset.titleHighlight)
                        : (asset.resolvedTitle ?? asset.title)}
                </div>

                {displayAttributes && (
                    <Attributes asset={asset} displayControls={true} />
                )}
            </div>
        </AssetItemWrapper>
    );
}
