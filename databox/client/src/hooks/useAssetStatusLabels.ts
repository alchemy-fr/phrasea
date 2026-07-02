import {useTranslation} from 'react-i18next';
import {useMemo} from 'react';
import {AssetStatus} from '../types.ts';

export function useAssetStatusLabels() {
    const {t} = useTranslation();

    return useMemo(
        () => ({
            [AssetStatus.Accepted]: t(
                'asset_status.choice.accepted',
                'Accepted'
            ),
            [AssetStatus.Pending]: t('asset_status.choice.pending', 'Pending'),
            [AssetStatus.Quarantined]: t(
                'asset_status.choice.quarantined',
                'Quarantined'
            ),
        }),
        [t]
    );
}
