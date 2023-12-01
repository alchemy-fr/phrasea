import {Asset} from '../../../types';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import ContentTab from '../Tabbed/ContentTab';
import {Button, Chip, Stack, styled, Typography} from '@mui/material';
import {triggerAssetWorkflow} from '../../../api/asset';
import {toast} from 'react-toastify';
import PowerSettingsNewIcon from '@mui/icons-material/PowerSettingsNew';
import {getWorkflows} from '../../../api/workflow';
import ModalLink from '../../Routing/ModalLink';
import moment from 'moment';
import {jobStatuses, Workflow} from '@alchemy/visual-workflow';
import React from 'react';

type Props = {
    data: Asset;
} & DialogTabProps;

const Section = styled('section')(({theme}) => ({
    marginBottom: theme.spacing(2),
}));

const Intro = styled('div')(({theme}) => ({
    marginBottom: theme.spacing(2),
}));

// Importing enum from visual-workflow does not work
enum JobStatus {
    Triggered = 0,
    Success = 1,
    Failure = 2,
    Skipped = 3,
    Running = 4,
    Error = 5,
}

export default function OperationsAsset({data, onClose, minHeight}: Props) {
    const [workflowTriggered, setWorkflowTriggered] = React.useState(false);
    const [workflows, setWorkflows] = React.useState<Workflow[]>();
    const triggerWorkflow = async () => {
        setWorkflowTriggered(true);
        await triggerAssetWorkflow(data.id);
        toast.success('Workflow is starting!');

        getWorkflows(data.id).then(setWorkflows);
    };

    React.useEffect(() => {
        getWorkflows(data.id).then(setWorkflows);
    }, []);

    const colors: Record<
        JobStatus,
        | 'info'
        | 'success'
        | 'error'
        | 'default'
        | 'warning'
        | 'primary'
        | 'secondary'
    > = {
        [JobStatus.Triggered]: 'secondary',
        [JobStatus.Success]: 'success',
        [JobStatus.Failure]: 'error',
        [JobStatus.Skipped]: 'default',
        [JobStatus.Running]: 'primary',
        [JobStatus.Error]: 'error',
    };

    return (
        <ContentTab onClose={onClose} minHeight={minHeight}>
            <Section>
                <Intro>You need to run integrations again?</Intro>
                <Button
                    onClick={triggerWorkflow}
                    disabled={workflowTriggered}
                    startIcon={<PowerSettingsNewIcon />}
                    variant={'contained'}
                >
                    Trigger workflow again
                </Button>
            </Section>
            <Section>
                <Intro>Last asset workflows</Intro>
                {workflows?.map(w => (
                    <Stack
                        key={w.id}
                        direction={'row'}
                        alignItems={'center'}
                        spacing={1}
                        sx={theme => ({
                            borderTop: `1px solid ${theme.palette.divider}`,
                            mt: 1,
                            pt: 1,
                        })}
                    >
                        <div>
                            <Typography variant={'body1'}>{w.name}</Typography>
                            <Typography variant={'body2'}>
                                {moment(w.startedAt).fromNow()}
                                {w.status !== undefined && (
                                    <Chip
                                        color={colors[w.status]}
                                        label={jobStatuses[w.status]}
                                        size={'small'}
                                        sx={{ml: 2}}
                                    />
                                )}
                            </Typography>
                        </div>
                        <Button
                            component={ModalLink}
                            routeName={'workflow_view'}
                            params={{
                                id: w.id,
                            }}
                        >
                            View
                        </Button>
                    </Stack>
                ))}
            </Section>
        </ContentTab>
    );
}
