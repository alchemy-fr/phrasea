import {Asset} from '../../types.ts';
import AssetItemWrapper from '../AssetList/Layouts/AssetItemWrapper.tsx';
import assetClasses from '../AssetList/classes.ts';
import {Checkbox} from '@mui/material';
import {stopPropagation} from '../../lib/stdFuncs.ts';
import React from 'react';
import AssetThumb from '../Media/Asset/AssetThumb.tsx';
import {OnPreviewToggle, OnToggle} from '../AssetList/types.ts';

type Props = {
    asset: Asset;
    selected: boolean;
    onToggle: OnToggle<Asset>;
    onPreviewToggle: OnPreviewToggle;
};

export default function AssetItem({
    asset,
    selected,
    onToggle,
    onPreviewToggle,
}: Props) {
    return (
        <AssetItemWrapper<Asset>
            item={asset}
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
                        onToggle(asset, {
                            ctrlKey: true,
                            preventDefault() {},
                        } as React.MouseEvent)
                    }
                />
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
        </AssetItemWrapper>
    );
}
