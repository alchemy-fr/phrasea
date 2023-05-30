import React, {MouseEventHandler} from 'react';
import {Asset} from "../../../types";
import {DialogTabProps} from "../Tabbed/TabbedDialog";
import ContentTab from "../Tabbed/ContentTab";
import {Button, styled} from "@mui/material";
import {triggerAssetWorkflow} from "../../../api/asset";
import {toast} from "react-toastify";
import PowerSettingsNewIcon from '@mui/icons-material/PowerSettingsNew';
import {Workflow} from "@alchemy/visual-workflow";
import {getWorkflows} from "../../../api/workflow";
import {getPath} from "../../../routes";
import {useNavigate} from "react-router-dom";
import ModalLink from "../../Routing/ModalLink";

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
    const [workflows, setWorkflows] = React.useState<Workflow[]>();
    const triggerWorkflow = async () => {
        setWorkflowTriggered(true);
        await triggerAssetWorkflow(data.id);
        toast.success('Workflow is starting!');
    }

    React.useEffect(() => {
        getWorkflows(data.id).then(setWorkflows);
    }, []);

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
        <Section>
            <Intro>
                Last asset workflows
            </Intro>
            {workflows?.map(w => <div
                key={w.id}
            >
                {w.name}
                <Button
                    component={ModalLink}
                    routeName={'workflow_view'}
                    params={{
                        id: w.id,
                    }}
                >
                    View
                </Button>
            </div>)}
        </Section>
    </ContentTab>
}
