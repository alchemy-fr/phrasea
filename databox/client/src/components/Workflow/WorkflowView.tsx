import {useEffect, useState} from 'react';
import {useParams} from 'react-router-dom';
import {getWorkflow, rerunJob} from '../../api/workflow';
import {Box, CircularProgress} from '@mui/material';
import {
    VisualWorkflow,
    Workflow,
    WorkflowHeader,
    WorkflowPlayground,
} from '@alchemy/visual-workflow';
import '@alchemy/visual-workflow/style.css';
import RouteDialog from '../Dialog/RouteDialog';
import AppDialog from '../Layout/AppDialog';

type Props = {};

const headerHeight = 78;

export default function WorkflowView({}: Props) {
    const {id} = useParams();
    const [data, setData] = useState<Workflow>();

    const onRefresh = React.useCallback(async () => {
        const d = await getWorkflow(id!);
        setData(d);
    }, [id]);

    const rerun = React.useCallback(
        async (jobId: string) => {
            const d = await rerunJob(id!, jobId);

            setData(d);
        },
        [id]
    );

    useEffect(() => {
        onRefresh();
    }, [onRefresh]);

    if (!data) {
        return <CircularProgress />;
    }

    return (
        <RouteDialog>
            {({open, onClose}) => (
                <AppDialog
                    open={open}
                    disablePadding={true}
                    sx={{
                        '.MuiDialogTitle-root': {
                            height: headerHeight,
                            maxHeight: headerHeight,
                        },
                    }}
                    fullScreen={true}
                    title={
                        <WorkflowPlayground
                            style={{
                                margin: -15,
                            }}
                        >
                            <Box
                                sx={theme => ({
                                    'position': 'absolute',
                                    'top': 0,
                                    'left': 0,
                                    'right': 60,
                                    'zIndex': theme.zIndex.appBar,
                                    '.workflow-header': {
                                        boxShadow: 'none',
                                    },
                                })}
                            >
                                <WorkflowHeader
                                    workflow={data}
                                    onRefreshWorkflow={onRefresh}
                                />
                            </Box>
                        </WorkflowPlayground>
                    }
                    onClose={onClose}
                >
                    <WorkflowPlayground
                        style={{
                            width: '100vw',
                            height: `calc(100vh - ${headerHeight + 2}px)`,
                        }}
                    >
                        <VisualWorkflow workflow={data} onRerunJob={rerun} />
                    </WorkflowPlayground>
                </AppDialog>
            )}
        </RouteDialog>
    );
}
