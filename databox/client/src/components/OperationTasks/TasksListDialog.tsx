import {
    Box,
    Button,
    Chip,
    LinearProgress,
    List,
    ListItem,
    ListItemText,
    Skeleton,
} from '@mui/material';
import React, {useMemo, useState} from 'react';
import {AppDialog} from '@alchemy/phrasea-ui';
import {useTranslation} from 'react-i18next';
import RouteDialog from '../Dialog/RouteDialog.tsx';
import {OperationTask, OperationTaskStatus} from '../../api/types.ts';
import {getTasks} from '../../api/operationTask.ts';
import RefreshIcon from '@mui/icons-material/Refresh';
import {NormalizedCollectionResponse} from '@alchemy/api';

type Props = {};

export default function TasksListDialog({}: Props) {
    const {t} = useTranslation();
    const [loading, setLoading] = useState(false);

    const [tasks, setTasks] =
        useState<NormalizedCollectionResponse<OperationTask>>();
    const reload = async () => {
        setLoading(true);
        try {
            setTasks(await getTasks());
        } finally {
            setLoading(false);
        }
    };

    React.useEffect(() => {
        reload();
    }, []);

    const statusLabels = useMemo(
        () => ({
            [OperationTaskStatus.Pending]: t(
                'operation_task.status.pending',
                'Pending'
            ),
            [OperationTaskStatus.InProgress]: t(
                'operation_task.status.in_progress',
                'In progress'
            ),
            [OperationTaskStatus.Completed]: t(
                'operation_task.status.completed',
                'Completed'
            ),
            [OperationTaskStatus.Failed]: t(
                'operation_task.status.failed',
                'Failed'
            ),
            [OperationTaskStatus.Cancelled]: t(
                'operation_task.status.cancelled',
                'Cancelled'
            ),
        }),
        [t]
    );

    const statusColors = useMemo(
        () => ({
            [OperationTaskStatus.Pending]: 'warning',
            [OperationTaskStatus.InProgress]: 'info',
            [OperationTaskStatus.Completed]: 'success',
            [OperationTaskStatus.Failed]: 'error',
            [OperationTaskStatus.Cancelled]: 'error',
        }),
        []
    );

    return (
        <RouteDialog>
            {({open, onClose}) => (
                <AppDialog
                    title={t('tasks_list.title', {
                        defaultValue: 'Task History',
                    })}
                    open={open}
                    maxWidth={'md'}
                    onClose={onClose}
                    loading={loading}
                    actions={({onClose, loading}) => {
                        return (
                            <>
                                <Button
                                    startIcon={<RefreshIcon />}
                                    onClick={reload}
                                    loading={loading}
                                    disabled={loading}
                                >
                                    {t('tasks_list.refresh', 'Refresh')}
                                </Button>
                                <Button onClick={onClose} disabled={loading}>
                                    {t('dialog.close', 'Close')}
                                </Button>
                            </>
                        );
                    }}
                >
                    {tasks ? (
                        <>
                            <List>
                                {tasks.result.map(t => {
                                    return (
                                        <ListItem
                                            key={t.id}
                                            sx={theme => ({
                                                borderBottom: `1px solid ${theme.palette.divider}`,
                                            })}
                                        >
                                            <ListItemText
                                                primary={
                                                    <>
                                                        <Box
                                                            sx={{
                                                                display:
                                                                    'inline-block',
                                                                mr: 1,
                                                            }}
                                                        >
                                                            {t.task}
                                                        </Box>
                                                        <Chip
                                                            label={
                                                                statusLabels[
                                                                    t.status
                                                                ]
                                                            }
                                                            sx={{
                                                                backgroundColor: `${statusColors[t.status]}.main`,
                                                                color: `${statusColors[t.status]}.contrastText`,
                                                            }}
                                                        />
                                                    </>
                                                }
                                                secondary={
                                                    <>
                                                        {t.status ===
                                                        OperationTaskStatus.InProgress ? (
                                                            <LinearProgress
                                                                sx={{
                                                                    mt: 1,
                                                                }}
                                                                title={
                                                                    t.remaining
                                                                }
                                                                variant={
                                                                    'determinate'
                                                                }
                                                                value={
                                                                    t.progression
                                                                }
                                                            />
                                                        ) : null}

                                                        <pre>
                                                            {JSON.stringify(
                                                                t.payload,
                                                                null,
                                                                2
                                                            )}
                                                        </pre>
                                                    </>
                                                }
                                            />
                                        </ListItem>
                                    );
                                })}
                            </List>
                        </>
                    ) : (
                        <>
                            <Skeleton />
                            <Skeleton />
                        </>
                    )}
                </AppDialog>
            )}
        </RouteDialog>
    );
}
