import {
    Accordion,
    AccordionDetails,
    AccordionSummary,
    Typography,
} from '@mui/material';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import {OnActiveAnnotations} from './Attribute/Attributes.tsx';
import React from 'react';
import {Asset} from '../../../types.ts';
import {useTranslation} from 'react-i18next';
import Thread from '../../Discussion/Thread.tsx';
import {OnNewAnnotationRef} from './Annotations/annotationTypes.ts';

type Props = {
    asset: Asset;
    onActiveAnnotations?: OnActiveAnnotations | undefined;
    onNewAnnotationRef?: OnNewAnnotationRef;
};

export default function AssetDiscussion({
    asset,
    onActiveAnnotations,
    onNewAnnotationRef,
}: Props) {
    const [expanded, setExpanded] = React.useState(true);
    const {t} = useTranslation();

    return (
        <Accordion expanded={expanded} onChange={() => setExpanded(p => !p)}>
            <AccordionSummary
                expandIcon={<ExpandMoreIcon />}
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
                    onNewAnnotationRef={onNewAnnotationRef}
                />
            </AccordionDetails>
        </Accordion>
    );
}
