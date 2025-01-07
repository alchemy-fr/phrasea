import {
    Accordion,
    AccordionDetails,
    AccordionSummary,
    Box,
    Typography,
} from '@mui/material';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import Attributes, {
    attributesSx,
    OnActiveAnnotations,
} from './Attribute/Attributes.tsx';
import React from 'react';
import {Asset} from '../../../types.ts';
import {useTranslation} from 'react-i18next';

type Props = {
    asset: Asset;
    onActiveAnnotations: OnActiveAnnotations | undefined;
};

export default function AssetAttributes({asset, onActiveAnnotations}: Props) {
    const [expanded, setExpanded] = React.useState(true);
    const {t} = useTranslation();

    return (
        <Box sx={attributesSx()}>
            <Accordion
                expanded={expanded}
                onChange={() => setExpanded(p => !p)}
            >
                <AccordionSummary
                    expandIcon={<ExpandMoreIcon />}
                    aria-controls="attr-content"
                    id="attr-header"
                >
                    <Typography component="div">
                        {t('asset.view.attributes', `Asset Attributes`)}
                    </Typography>
                </AccordionSummary>
                <AccordionDetails>
                    <Attributes
                        asset={asset}
                        displayControls={true}
                        onActiveAnnotations={onActiveAnnotations}
                    />
                </AccordionDetails>
            </Accordion>
        </Box>
    );
}
