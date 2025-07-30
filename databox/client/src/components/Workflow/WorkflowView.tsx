import React, {useEffect, useState} from 'react';
import {NavigateToOverlayProps, useParams} from '@alchemy/navigation';
import {cancelWorkflow, getWorkflow, rerunJob} from '../../api/workflow';
import {Box, CircularProgress} from '@mui/material';
import {
    VisualWorkflow,
    Workflow,
    WorkflowHeader,
    WorkflowPlayground,
} from '@alchemy/visual-workflow';
import RouteDialog from '../Dialog/RouteDialog';
import {AppDialog} from '@alchemy/phrasea-ui';
import {useChannelRegistration} from '../../lib/pusher.ts';
import {modalRoutes} from '../../routes.ts';

type Props = {};

const headerHeight = 78;

export default function WorkflowView({}: Props) {
    const {id, assetId} = useParams();
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

    const onCancel = React.useCallback(async () => {
        const d = await cancelWorkflow(id!);

        setData(d);
    }, [id]);

    useEffect(() => {
        onRefresh();
    }, [onRefresh]);

    useChannelRegistration(
        `workflow-${id}`,
        'job_update',
        () => {
            onRefresh();
        },
        !!data
    );

    if (!data) {
        return <CircularProgress />;
    }

    return (
        <RouteDialog
            previousLocation={
                assetId
                    ? ({
                          route: modalRoutes.assets.routes.manage,
                          params: {
                              id: assetId,
                              tab: 'workflow',
                          },
                      } as NavigateToOverlayProps)
                    : undefined
            }
        >
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
                                    onCancel={onCancel}
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
