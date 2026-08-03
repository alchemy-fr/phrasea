import {useEffect, useState} from 'react';
import {Box, CircularProgress, Typography} from '@mui/material';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {AppDialog} from '@alchemy/phrasea-ui';
import {useTranslation} from 'react-i18next';
import {Asset} from '../../../../types.ts';
import {getSimilarAssets, SimilarAssetsResult} from '../../../../api/asset.ts';
import AssetThumb, {thumbSx} from '../AssetThumb.tsx';
import {useNavigateToModal} from '../../../Routing/ModalLink.tsx';
import {modalRoutes, Routing} from '../../../../routes.ts';

type Props = {
    asset: Asset;
} & StackedModalProps;

export default function SimilarAssetsDialog({asset, open, modalIndex}: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();
    const navigateToModal = useNavigateToModal();
    const [result, setResult] = useState<SimilarAssetsResult | undefined>();

    useEffect(() => {
        getSimilarAssets(asset.id, 50).then(setResult);
    }, [asset.id]);

    return (
        <AppDialog
            maxWidth={'md'}
            modalIndex={modalIndex}
            open={open}
            loading={!result}
            onClose={closeModal}
            title={t('similar_assets.dialog.title', {
                defaultValue: `Assets similar to {{name}}`,
                name: asset.name ?? '',
            })}
        >
            {!result ? (
                <Box sx={{p: 4, textAlign: 'center'}}>
                    <CircularProgress />
                </Box>
            ) : 0 === result.assets.length ? (
                <Typography color={'text.secondary'}>
                    {t('similar_assets.dialog.empty', `No similar asset found`)}
                </Typography>
            ) : (
                <Box
                    sx={theme => ({
                        display: 'flex',
                        flexWrap: 'wrap',
                        gap: 2,
                        ...thumbSx(134, theme),
                    })}
                >
                    {result.assets.map(a => (
                        <Box
                            key={a.id}
                            onClick={() => {
                                closeModal();
                                navigateToModal(
                                    modalRoutes.assets.routes.view,
                                    {
                                        id: a.id,
                                        renditionId:
                                            a.main?.id ||
                                            Routing.UnknownRendition,
                                    }
                                );
                            }}
                            sx={theme => ({
                                'cursor': 'pointer',
                                'width': 150,
                                'borderRadius': 1,
                                'p': 1,
                                '&:hover': {
                                    backgroundColor: theme.palette.action.hover,
                                },
                            })}
                        >
                            <AssetThumb asset={a} noStoryCarousel={true} />
                            <Typography
                                variant={'body2'}
                                noWrap={true}
                                title={a.name}
                            >
                                {a.name}
                            </Typography>
                            {undefined !== result.scores[a.id] ? (
                                <Typography
                                    variant={'caption'}
                                    color={'text.secondary'}
                                >
                                    {`${Math.round(
                                        result.scores[a.id] * 100
                                    )}%`}
                                </Typography>
                            ) : null}
                        </Box>
                    ))}
                </Box>
            )}
        </AppDialog>
    );
}
