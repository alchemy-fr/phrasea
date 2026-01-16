import {
    Accordion,
    AccordionDetails,
    AccordionSummary,
    Typography,
} from '@mui/material';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import {useEffect, useState, memo} from 'react';
import {Asset, Stat} from '../../../types.ts';
import {useTranslation} from 'react-i18next';
import {BaseAttributeRowUIProps} from './Attribute/AttributeRowUI.tsx';
import {getAssetStats} from '../../../api/asset.ts';
import AssetMatomoMetricsList from './AssetMatomoMetricsList.tsx';

type Props = {
    asset: Asset;
} & BaseAttributeRowUIProps;

function AssetMatomoMetricsView({asset}: Props) {
    const [expanded, setExpanded] = useState(false);
    const [stats, setStats] = useState<Stat | null>(null);
    const {t} = useTranslation();

    useEffect(() => {
        const process = async () => {
            const res = await getAssetStats(asset.id, {
                type: asset.source?.type,
            });

            if (Object.hasOwnProperty.call(res, 'nb_impressions')) {
                setStats(res);
            }
        };

        if (expanded) {
            process();
        }
    }, [expanded]);

    return (
        <Accordion expanded={expanded} onChange={() => setExpanded(p => !p)}>
            <AccordionSummary expandIcon={<ExpandMoreIcon />}>
                <Typography component="div">
                    {t('asset.view.metric', `Matomo Metric`)}
                </Typography>
            </AccordionSummary>
            <AccordionDetails>
                <AssetMatomoMetricsList
                    data={stats}
                    type={asset.source?.type}
                />
            </AccordionDetails>
        </Accordion>
    );
}

export default memo(AssetMatomoMetricsView);
