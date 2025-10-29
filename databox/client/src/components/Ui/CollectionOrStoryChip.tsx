import {Collection} from '../../types.ts';
import CollectionStoryChip, {
    CollectionStoryChipProps,
} from './CollectionStoryChip.tsx';
import {CollectionChip} from './CollectionChip.tsx';

type Props = {
    collection: Collection;
} & Omit<CollectionStoryChipProps, 'storyAsset'>;

export default function CollectionOrStoryChip({
    collection,
    asset,
    onOpen,
    ...chipProps
}: Props) {
    if (collection.storyAsset) {
        return (
            <CollectionStoryChip
                storyAsset={collection.storyAsset}
                asset={asset}
                onOpen={onOpen}
                {...chipProps}
            />
        );
    }

    return <CollectionChip collection={collection} {...chipProps} />;
}
