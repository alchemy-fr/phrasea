import {
    Accordion,
    AccordionDetails,
    AccordionSummary,
    Typography,
} from '@mui/material';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import React, {memo} from 'react';
import {Asset} from '../../../types.ts';
import {useTranslation} from 'react-i18next';
import {BaseAttributeRowUIProps} from './Attribute/AttributeRowUI.tsx';
import AssetInfoList from './AssetInfoList.tsx';

type Props = {
    asset: Asset;
} & BaseAttributeRowUIProps;

function AssetViewInfo({asset}: Props) {
    const [expanded, setExpanded] = React.useState(true);
    const {t} = useTranslation();

    return (
        <Accordion expanded={expanded} onChange={() => setExpanded(p => !p)}>
            <AccordionSummary expandIcon={<ExpandMoreIcon />}>
                <Typography component="div">
                    {t('asset.view.info', `Information`)}
                </Typography>
            </AccordionSummary>
            <AccordionDetails>
                <AssetInfoList data={asset} />
            </AccordionDetails>
        </Accordion>
    );
}

export default memo(AssetViewInfo);
