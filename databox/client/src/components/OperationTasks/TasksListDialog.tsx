import {
    Button,
    Chip,
    LinearProgress,
    List,
    ListItem,
    ListItemText,
    Skeleton,
} from '@mui/material';
import React, {useMemo, useState} from 'react';
import {AppDialog, FlexRow} from '@alchemy/phrasea-ui';
import {useTranslation} from 'react-i18next';
import RouteDialog from '../Dialog/RouteDialog.tsx';
import {OperationTask, OperationTaskStatus} from '../../api/types.ts';
import {getTasks} from '../../api/operationTask.ts';
import RefreshIcon from '@mui/icons-material/Refresh';
import {NormalizedCollectionResponse} from '@alchemy/api';
import {useNavigateToModal} from '../Routing/ModalLink.tsx';
import {modalRoutes} from '../../routes.ts';
import {User} from '../../types.ts';

type Props = {};

export default function TasksListDialog({}: Props) {
    const {t} = useTranslation();
    const navigateToModal = useNavigateToModal();
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

    tasks?.result.push({
        id: '42',
        task: 'test',
        startedAt: '2026-05-15T12:00:00Z',
        status: OperationTaskStatus.InProgress,
        progression: 50,
        remaining: '1 minute',
        payload: {
            foo: 'bar',
        },
        createdAt: '2026-05-15T12:00:00Z',
        owner: {
            id: '123',
            username: 'test',
        } as User,
        endedAt: '2026-05-15T12:00:00Z',
    });

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
                                    variant="contained"
                                    onClick={() => {
                                        navigateToModal(
                                            modalRoutes.operationTasks.routes
                                                .create
                                        );
                                    }}
                                >
                                    {t('tasks_list.create', 'New Task')}
                                </Button>
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
                                                    <FlexRow
                                                        sx={{
                                                            gap: 1,
                                                        }}
                                                    >
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
                                                        <div>{t.task}</div>
                                                        <div>{t.startedAt}</div>
                                                    </FlexRow>
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

                                                        <pre
                                                            style={{
                                                                fontSize: 11,
                                                            }}
                                                        >
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
