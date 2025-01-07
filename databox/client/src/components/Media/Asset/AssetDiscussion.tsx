import {Accordion, AccordionDetails, AccordionSummary, Box, Typography,} from '@mui/material';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import {attributesSx, OnActiveAnnotations,} from './Attribute/Attributes.tsx';
import React from 'react';
import {Asset} from '../../../types.ts';
import {useTranslation} from 'react-i18next';
import Thread from "../../Discussion/Thread.tsx";

type Props = {
    asset: Asset;
    onActiveAnnotations: OnActiveAnnotations | undefined;
};

export default function AssetDiscussion({asset,onActiveAnnotations}: Props) {
    const [expanded, setExpanded] = React.useState(true);
    const {t} = useTranslation();

    return (
        <Box sx={attributesSx()}>
            <Accordion
                expanded={expanded}
                onChange={() => setExpanded(p => !p)}
            >
                <AccordionSummary
                    expandIcon={<ExpandMoreIcon/>}
                    aria-controls="attr-content"
                    id="attr-header"
                >
                    <Typography component="div">
                        {t('asset.view.discussion', `Discussion`)}
                    </Typography>
                </AccordionSummary>
                <AccordionDetails>
                    <Thread
                        threadKey={asset.threadKey}
                        threadId={asset.thread?.id}
                        onActiveAnnotations={onActiveAnnotations}
                    />
                </AccordionDetails>
            </Accordion>
        </Box>
    );
}
