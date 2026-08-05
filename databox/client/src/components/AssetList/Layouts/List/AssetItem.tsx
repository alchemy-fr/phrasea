import {MouseEvent} from 'react';
import {AssetOrAssetContainer, AssetStatus} from '../../../../types';
import assetClasses from '../../classes';
import IconButton from '@mui/material/IconButton';
import MoreVertIcon from '@mui/icons-material/MoreVert';
import AssetThumb from '../../../Media/Asset/AssetThumb';
import Attributes from '../../../Media/Asset/Attribute/Attributes';
import {AssetItemProps, OnPreviewToggle} from '../../types';
import {Checkbox} from '@mui/material';
import {stopPropagation} from '../../../../lib/stdFuncs';
import AssetItemWrapper from '../AssetItemWrapper';
import QuarantineActions from '../../../Media/Asset/QuarantineActions.tsx';

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
            <div>
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
                        } as MouseEvent)
                    }
                />
                {!disabled ? (
                    <div className={assetClasses.controls}>
                        {onContextMenuOpen && (
                            <IconButton
                                className={assetClasses.settingBtn}
                                onClick={e => onContextMenuOpen(e, item)}
                                color={'inherit'}
                            >
                                <MoreVertIcon
                                    color={'inherit'}
                                    fontSize={'small'}
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
                {asset.status === AssetStatus.Quarantined && (
                    <QuarantineActions asset={asset} />
                )}
                <Attributes asset={asset} displayControls={true} />
            </div>
        </AssetItemWrapper>
    );
}
