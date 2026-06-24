import {Button, List, Skeleton} from '@mui/material';
import React, {useState} from 'react';
import {AppDialog} from '@alchemy/phrasea-ui';
import {useTranslation} from 'react-i18next';
import RouteDialog from '../Dialog/RouteDialog.tsx';
import {OperationTask} from '../../api/types.ts';
import {getTasks} from '../../api/operationTask.ts';
import RefreshIcon from '@mui/icons-material/Refresh';
import {NormalizedCollectionResponse} from '@alchemy/api';
import {useNavigateToModal} from '../Routing/ModalLink.tsx';
import {modalRoutes} from '../../routes.ts';
import TaskCard from './TaskCard.tsx';
import {useTasks} from './tasks/useTasks.ts';

type Props = {};

export default function TasksListDialog({}: Props) {
    const {t} = useTranslation();
    const navigateToModal = useNavigateToModal();
    const [loading, setLoading] = useState(false);
    const tasksRegistry = useTasks();

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
                                    const tDef = tasksRegistry.find(
                                        task => task.name === t.task
                                    );

                                    return (
                                        <TaskCard
                                            key={t.id}
                                            title={tDef?.displayName ?? t.task}
                                            task={t}
                                        />
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
