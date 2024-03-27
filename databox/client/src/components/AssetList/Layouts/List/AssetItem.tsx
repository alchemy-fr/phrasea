import {MouseEvent} from 'react';
import {AssetOrAssetContainer} from '../../../../types.ts';
import assetClasses from '../../classes.ts';
import PrivacyChip from '../../../Ui/PrivacyChip.tsx';
import IconButton from '@mui/material/IconButton';
import ShoppingCartIcon from '@mui/icons-material/ShoppingCart';
import SettingsIcon from '@mui/icons-material/Settings';
import AssetThumb from '../../../Media/Asset/AssetThumb.tsx';
import Attributes, {
    replaceHighlight,
} from '../../../Media/Asset/Attribute/Attributes.tsx';
import AssetTagList from '../../../Media/Asset/Widgets/AssetTagList.tsx';
import AssetCollectionList from '../../../Media/Asset/Widgets/AssetCollectionList.tsx';
import {AssetItemProps, OnPreviewToggle} from '../../types.ts';
import {Checkbox, Grid} from '@mui/material';
import {stopPropagation} from '../../../../lib/stdFuncs.ts';
import AssetItemWrapper from '../AssetItemWrapper.tsx';

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
            <Grid container spacing={2} wrap={'nowrap'}>
                <Grid item>
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
                    />
                </Grid>
                <Grid item className={assetClasses.attributes}>
                    <div className={assetClasses.title}>
                        {asset.titleHighlight
                            ? replaceHighlight(asset.titleHighlight)
                            : asset.resolvedTitle ?? asset.title}
                    </div>
                    {asset.tags && asset.tags.length > 0 && (
                        <div>
                            <AssetTagList tags={asset.tags} />
                        </div>
                    )}
                    <PrivacyChip
                        privacy={asset.privacy}
                        size={'small'}
                        noAccess={disabled}
                    />
                    {asset.collections && asset.collections.length > 0 ? (
                        <div>
                            <AssetCollectionList
                                workspace={asset.workspace}
                                collections={asset.collections}
                            />
                        </div>
                    ) : (
                        ''
                    )}
                    {displayAttributes && (
                        <Attributes asset={asset} displayControls={true} />
                    )}
                </Grid>
            </Grid>
        </AssetItemWrapper>
    );
}
