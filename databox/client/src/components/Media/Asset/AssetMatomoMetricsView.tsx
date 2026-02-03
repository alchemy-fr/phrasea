import {
    Accordion,
    AccordionDetails,
    AccordionSummary,
    Typography,
} from '@mui/material';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import {useEffect, useState, memo} from 'react';
import {Asset, MatomoMediaMetrics} from '../../../types.ts';
import {useTranslation} from 'react-i18next';
import {BaseAttributeRowUIProps} from './Attribute/AttributeRowUI.tsx';
import {getAssetMetrics} from '../../../api/asset.ts';
import AssetMatomoMetricsList from './AssetMatomoMetricsList.tsx';
import {config} from '../../../init.ts';

type Props = {
    asset: Asset;
} & BaseAttributeRowUIProps;

function AssetMatomoMetricsView({asset}: Props) {
    const [expanded, setExpanded] = useState(false);
    const [stats, setStats] = useState<MatomoMediaMetrics | null>(null);
    const {t} = useTranslation();

    const enabled = !!config.analytics?.matomo;

    useEffect(() => {
        if (expanded) {
            (async () => {
                const res = await getAssetMetrics(asset.id, asset.source?.type);

                if (Object.hasOwnProperty.call(res, 'nb_impressions')) {
                    setStats(res);
                } else {
                    setStats(null);
                }
            })();
        }
    }, [expanded, asset]);

    return (
        <Accordion expanded={expanded} onChange={() => setExpanded(p => !p)}>
            <AccordionSummary expandIcon={<ExpandMoreIcon />}>
                <Typography component="div">
                    {t('asset.view.metrics.title', `Metrics`)}
                </Typography>
            </AccordionSummary>
            <AccordionDetails>
                {enabled ? (
                    <AssetMatomoMetricsList
                        data={stats}
                        type={asset.source?.type}
                        mediaPluginEnabled={
                            config.analytics!.matomo!.mediaPluginEnabled
                        }
                    />
                ) : (
                    <Typography>
                        {t(
                            'asset.view.metrics.not_configured',
                            'Matomo analytics is not configured.'
                        )}
                    </Typography>
                )}
            </AccordionDetails>
        </Accordion>
    );
}

export default memo(AssetMatomoMetricsView);
