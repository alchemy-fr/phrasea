import {Chip, ChipProps} from '@mui/material';
import {Asset} from '../../types.ts';
import {OnOpen} from '../AssetList/types.ts';
import LayersIcon from '@mui/icons-material/Layers';

type Props = {
    asset?: Asset;
    storyAsset: Asset;
    onOpen?: OnOpen;
} & ChipProps;

export default function CollectionStoryChip({
    asset,
    storyAsset,
    onOpen,
    ...chipProps
}: Props) {
    return (
        <Chip
            onClick={
                asset && onOpen
                    ? () => onOpen(storyAsset, undefined, asset!.id)
                    : undefined
            }
            color={'warning'}
            icon={<LayersIcon />}
            label={storyAsset?.resolvedTitle ?? storyAsset.title}
            {...chipProps}
        />
    );
}
