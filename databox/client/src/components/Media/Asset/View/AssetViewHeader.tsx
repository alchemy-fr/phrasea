import AssetViewNavigation from '../AssetViewNavigation.tsx';
import {Trans} from 'react-i18next';
import {Select} from '@mui/material';
import {Asset, AssetRendition} from '../../../../types.ts';
import MenuItem from '@mui/material/MenuItem';
import AssetViewActions from '../Actions/AssetViewActions.tsx';
import {FlexRow} from '@alchemy/phrasea-ui';
import {useLocation} from '@alchemy/navigation';
import type {Location} from '@alchemy/navigation';
import {memo} from 'react';
import {modalRoutes} from '../../../../routes.ts';
import {useNavigateToModal} from '../../../Routing/ModalLink.tsx';
import {AssetContextState} from '../assetTypes.ts';

type Props = {
    asset: Asset;
    rendition: AssetRendition | undefined;
    renditions: AssetRendition[];
    displayActions: boolean;
};

function AssetViewHeader({
    asset,
    rendition,
    displayActions,
    renditions,
}: Props) {
    const {state} = useLocation() as Location<AssetContextState | undefined>;
    const navigateToModal = useNavigateToModal();
    const handleRenditionChange = (renditionId: string) => {
        navigateToModal(modalRoutes.assets.routes.view, {
            id: asset.id,
            renditionId,
        });
    };

    return (
        <FlexRow alignItems={'center'}>
            <AssetViewNavigation state={state} currentId={asset.id} />
            <div>
                <Trans
                    i18nKey={'asset_view.edit_asset'}
                    values={{
                        name: asset.resolvedTitle,
                    }}
                    defaults={'Asset <strong>{{name}}</strong>'}
                />
            </div>
            <Select<string>
                sx={{ml: 2}}
                label={''}
                size={'small'}
                value={rendition?.id}
                onChange={e => handleRenditionChange(e.target.value)}
            >
                {renditions.map((r: AssetRendition) => (
                    <MenuItem key={r.id} value={r.id}>
                        {r.name}
                    </MenuItem>
                ))}
            </Select>
            {displayActions ? (
                <AssetViewActions asset={asset} file={rendition?.file} />
            ) : (
                ''
            )}
        </FlexRow>
    );
}

export default memo(AssetViewHeader);
