import {
    Accordion,
    AccordionDetails,
    AccordionSummary,
    Box,
    Typography,
} from '@mui/material';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import Attributes, {attributesSx} from './Attribute/Attributes.tsx';
import React, {memo} from 'react';
import {Asset} from '../../../types.ts';
import {useTranslation} from 'react-i18next';
import {BaseAttributeRowUIProps} from './Attribute/AttributeRowUI.tsx';

type Props = {
    asset: Asset;
} & BaseAttributeRowUIProps;

function AssetAttributes({asset, ...attributesProps}: Props) {
    const [expanded, setExpanded] = React.useState(false);
    const {t} = useTranslation();

    return (
        <Accordion expanded={expanded} onChange={() => setExpanded(p => !p)}>
            <AccordionSummary expandIcon={<ExpandMoreIcon />}>
                <Typography component="div">
                    {t('asset.view.attributes', `Attributes`)}
                </Typography>
            </AccordionSummary>
            <AccordionDetails>
                <Box sx={attributesSx()}>
                    <Attributes
                        {...attributesProps}
                        asset={asset}
                        displayControls={true}
                    />
                </Box>
            </AccordionDetails>
        </Accordion>
    );
}

export default memo(AssetAttributes);
