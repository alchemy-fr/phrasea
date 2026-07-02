import {TFunction} from '@alchemy/i18n';
import {AssetStatus} from '../types.ts';

export function getAssetStatusTranslations(
    t: TFunction
): Record<AssetStatus, string> {
    return {
        [AssetStatus.Accepted]: t('asset_status.choice.accepted', 'Accepted'),
        [AssetStatus.Pending]: t('asset_status.choice.pending', 'Pending'),
        [AssetStatus.Quarantined]: t(
            'asset_status.choice.quarantined',
            'Quarantined'
        ),
    };
}
