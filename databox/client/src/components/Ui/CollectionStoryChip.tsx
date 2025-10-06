import BurstModeIcon from '@mui/icons-material/BurstMode';
import {Chip} from '@mui/material';
import {Asset} from '../../types.ts';
import {OnOpen} from '../AssetList/types.ts';

type Props = {
    asset: Asset;
    storyAsset: Asset;
    onOpen?: OnOpen;
};

export default function CollectionStoryChip({
    asset,
    storyAsset,
    onOpen,
}: Props) {
    return (
        <Chip
            onClick={() => onOpen?.(storyAsset, undefined, asset.id)}
            size={'small'}
            color={'warning'}
            icon={<BurstModeIcon />}
            label={storyAsset?.resolvedTitle ?? storyAsset.title}
        />
    );
}
