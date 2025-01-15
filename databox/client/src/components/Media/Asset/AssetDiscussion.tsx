import {
    Accordion,
    AccordionDetails,
    AccordionSummary,
    Typography,
} from '@mui/material';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import {OnActiveAnnotations} from './Attribute/Attributes.tsx';
import React, {memo} from 'react';
import {Asset} from '../../../types.ts';
import {useTranslation} from 'react-i18next';
import Thread from '../../Discussion/Thread.tsx';
import {AnnotationsControlRef} from './Annotations/annotationTypes.ts';

type Props = {
    asset: Asset;
    onActiveAnnotations?: OnActiveAnnotations | undefined;
    annotationsControlRef?: AnnotationsControlRef;
};

function AssetDiscussion({
    asset,
    onActiveAnnotations,
    annotationsControlRef,
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
                    annotationsControlRef={annotationsControlRef}
                />
            </AccordionDetails>
        </Accordion>
    );
}

export default memo(AssetDiscussion, (a, b) => {
    return a.asset.id === b.asset.id;
});
