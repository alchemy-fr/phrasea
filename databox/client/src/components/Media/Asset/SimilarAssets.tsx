import {
    Accordion,
    AccordionDetails,
    AccordionSummary,
    CircularProgress,
    ListItemButton,
    ListItemIcon,
    ListItemText,
    MenuList,
    Typography,
} from '@mui/material';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import {memo, useEffect, useState} from 'react';
import {Asset} from '../../../types.ts';
import {useTranslation} from 'react-i18next';
import AssetThumb, {thumbSx} from './AssetThumb.tsx';
import {useNavigateToModal} from '../../Routing/ModalLink.tsx';
import {modalRoutes, Routing} from '../../../routes.ts';
import {getSimilarAssets, SimilarAssetsResult} from '../../../api/asset.ts';

type Props = {
    asset: Asset;
};

function SimilarAssets({asset}: Props) {
    const [expanded, setExpanded] = useState(false);
    const [result, setResult] = useState<SimilarAssetsResult | undefined>();
    const {t} = useTranslation();
    const navigateToModal = useNavigateToModal();

    useEffect(() => {
        setResult(undefined);
    }, [asset.id]);

    useEffect(() => {
        if (expanded) {
            getSimilarAssets(asset.id).then(setResult);
        }
    }, [expanded, asset.id]);

    return (
        <Accordion expanded={expanded} onChange={() => setExpanded(p => !p)}>
            <AccordionSummary expandIcon={<ExpandMoreIcon />}>
                <Typography component="div">
                    {t('asset.view.similar_assets', `Similar assets`)}
                </Typography>
            </AccordionSummary>
            <AccordionDetails
                sx={{
                    p: 0,
                }}
            >
                {expanded && !result ? (
                    <Typography
                        component={'div'}
                        sx={{p: 2, textAlign: 'center'}}
                    >
                        <CircularProgress size={24} />
                    </Typography>
                ) : null}
                {result && 0 === result.assets.length ? (
                    <Typography sx={{p: 2}} color={'text.secondary'}>
                        {t(
                            'asset.view.similar_assets_empty',
                            `No similar asset found`
                        )}
                    </Typography>
                ) : null}
                <MenuList
                    sx={theme => ({
                        ...thumbSx(200, theme),
                        '.MuiListItemText-root': {
                            pl: 1,
                        },
                    })}
                    disablePadding={true}
                >
                    {result?.assets.map(a => (
                        <ListItemButton
                            key={a.id}
                            onClick={() => {
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
                        >
                            <ListItemIcon>
                                <AssetThumb asset={a} noStoryCarousel={true} />
                            </ListItemIcon>
                            <ListItemText
                                primary={a.name}
                                secondary={
                                    undefined !== result.scores[a.id]
                                        ? `${Math.round(
                                              result.scores[a.id] * 100
                                          )}%`
                                        : undefined
                                }
                            />
                        </ListItemButton>
                    ))}
                </MenuList>
            </AccordionDetails>
        </Accordion>
    );
}

export default memo(SimilarAssets);
