import {Accordion, AccordionDetails, AccordionSummary, Typography,} from '@mui/material';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import React, {memo} from 'react';
import {Asset} from '../../../types.ts';
import {useTranslation} from 'react-i18next';
import Thread, {BaseThreadProps} from '../../Discussion/Thread.tsx';

type Props = {
    asset: Asset;
} & BaseThreadProps;

function AssetDiscussion({
    asset,
    ...threadProps
}: Props) {
    const [expanded, setExpanded] = React.useState(true);
    const {t} = useTranslation();

    return (
        <Accordion expanded={expanded} onChange={() => setExpanded(p => !p)}>
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
                    {...threadProps}
                    threadKey={asset.threadKey}
                    threadId={asset.thread?.id}
                />
            </AccordionDetails>
        </Accordion>
    );
}

export default memo(AssetDiscussion);
