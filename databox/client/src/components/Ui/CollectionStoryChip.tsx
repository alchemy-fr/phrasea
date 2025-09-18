import BurstModeIcon from '@mui/icons-material/BurstMode';
import {Chip} from '@mui/material';
import {Asset} from '../../types.ts';

type Props = {
    storyAsset: Asset;
    onOpen?: (asset: Asset) => void;
};

export default function CollectionStoryChip({storyAsset, onOpen}: Props) {
    return (
        <Chip
            onClick={() => onOpen?.(storyAsset)}
            size={'small'}
            color={'warning'}
            icon={<BurstModeIcon />}
            label={storyAsset?.title}
        />
    );
}
