import {Asset} from '../../../../types.ts';
import AssetThumb, {thumbSx} from '../AssetThumb.tsx';
import {Box, Skeleton} from '@mui/material';
import assetClasses from '../../../AssetList/classes.ts';
import classNames from 'classnames';
import {NormalizedCollectionResponse} from '@alchemy/api';

type Props = {
    story: Asset;
    selectedAsset?: Asset | undefined;
    assets: NormalizedCollectionResponse<Asset> | undefined;
    onAssetClick: (asset: Asset) => void;
};

export const storyCarouselHeight = 100;

export default function StoryCarousel({
    selectedAsset,
    story,
    assets,
    onAssetClick,
}: Props) {
    return (
        <>
            <Box
                sx={theme => {
                    const h =
                        storyCarouselHeight - parseInt(theme.spacing(1)) * 2;

                    return {
                        ...thumbSx(h, theme),
                        overflowX: 'auto',
                        overflowY: 'hidden',
                        bgcolor: 'background.paper',
                        borderTop: `1px solid ${theme.palette.divider}`,
                        ['.selected']: {
                            boxShadow: `inset 0 0 0 2px ${theme.palette.primary.main}`,
                        },
                        ['> div']: {
                            display: 'inline-flex',
                            flexDirection: 'row',
                            gap: 1,
                            m: 1,
                            ['> div']: {
                                flex: '0 0 auto',
                                cursor: 'pointer',
                            },
                        },
                    };
                }}
            >
                <div>
                    <div
                        onClick={() => onAssetClick(story)}
                        className={classNames({
                            [assetClasses.thumbWrapper]: true,
                            selected: selectedAsset?.id === story.id,
                        })}
                    >
                        <AssetThumb asset={story} />
                    </div>
                    {!assets ? (
                        <>
                            {new Array(10).fill(0).map((_, i) => (
                                <Skeleton
                                    key={i}
                                    variant="rectangular"
                                    width={storyCarouselHeight}
                                    height={storyCarouselHeight}
                                />
                            ))}
                        </>
                    ) : (
                        <>
                            {assets.result.map(asset => (
                                <div
                                    key={asset.id}
                                    onClick={() => onAssetClick(asset)}
                                    className={classNames({
                                        [assetClasses.thumbWrapper]: true,
                                        selected:
                                            selectedAsset?.id === asset.id,
                                    })}
                                >
                                    <AssetThumb asset={asset} />
                                </div>
                            ))}
                            {assets.result.length < assets.total && (
                                <div>
                                    +{assets.total - assets.result.length} more
                                </div>
                            )}
                        </>
                    )}
                </div>
            </Box>
        </>
    );
}
