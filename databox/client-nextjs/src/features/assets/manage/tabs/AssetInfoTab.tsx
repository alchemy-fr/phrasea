'use client';

import {AssetInfoList} from '@/features/assets/view/AssetInfoList';
import type {AssetTabProps} from '../AssetManageRoute';

export function AssetInfoTab({asset}: AssetTabProps) {
    return (
        <div className="max-w-2xl">
            <AssetInfoList asset={asset} />
        </div>
    );
}
