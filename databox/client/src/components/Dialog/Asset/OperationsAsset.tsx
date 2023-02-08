import React from 'react';
import {Asset} from "../../../types";
import {DialogTabProps} from "../Tabbed/TabbedDialog";
import ContentTab from "../Tabbed/ContentTab";
import {Button, styled, Typography} from "@mui/material";
import {triggerAssetWorkflow} from "../../../api/asset";
import {toast} from "react-toastify";
import PowerSettingsNewIcon from '@mui/icons-material/PowerSettingsNew';

type Props = {
    data: Asset;
} & DialogTabProps;

const Section = styled('section')(({theme}) => ({
    marginBottom: theme.spacing(2),
}));

const Intro = styled('div')(({theme}) => ({
    marginBottom: theme.spacing(2),
}));

export default function OperationsAsset({
    data,
    onClose,
    minHeight,
}: Props) {
    const [workflowTriggered, setWorkflowTriggered] = React.useState(false);
    const triggerWorkflow = async () => {
        setWorkflowTriggered(true);
        await triggerAssetWorkflow(data.id);
        toast.success('Workflow is starting!');
    }

    return <ContentTab
        onClose={onClose}
        minHeight={minHeight}
    >
        <Section>
            <Intro>
                You need to run integrations again?
            </Intro>
            <Button
                onClick={triggerWorkflow}
                disabled={workflowTriggered}
                startIcon={<PowerSettingsNewIcon/>}
                variant={'contained'}
            >
                Trigger workflow again
            </Button>
        </Section>
    </ContentTab>
}
