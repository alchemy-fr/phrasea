import {Button, Skeleton} from '@mui/material';
import React, {useState} from 'react';
import {AppDialog} from '@alchemy/phrasea-ui';
import {useTranslation} from 'react-i18next';
import RouteDialog from '../Dialog/RouteDialog.tsx';
import {OperationTask} from '../../api/types.ts';
import {getTask} from '../../api/operationTask.ts';
import {useTasks} from './tasks/useTasks.ts';
import {useParams} from '@alchemy/navigation';
import InfoRow from '../Dialog/Info/InfoRow.tsx';
import EventIcon from '@mui/icons-material/Event';
import PersonIcon from '@mui/icons-material/Person';
import {User} from '../../types.ts';
import DataObjectIcon from '@mui/icons-material/DataObject';
import OutputIcon from '@mui/icons-material/Output';
import TimerIcon from '@mui/icons-material/Timer';
import moment from 'moment/moment';
import {formatDuration} from '../../lib/duration.ts';
import TaskIcon from '@mui/icons-material/Task';
import NumbersIcon from '@mui/icons-material/Numbers';
import FunctionsIcon from '@mui/icons-material/Functions';

type Props = {};

export default function TaskDetailsDialog({}: Props) {
    const {t} = useTranslation();
    const {id} = useParams();
    const tasksRegistry = useTasks();
    const [task, setTask] = useState<OperationTask>();

    React.useEffect(() => {
        getTask(id!).then(setTask);
    }, [id]);

    const taskDef = tasksRegistry.find(t => t.name === task?.task);

    return (
        <RouteDialog>
            {({open, onClose}) => (
                <AppDialog
                    title={
                        taskDef
                            ? t('tasks_list.title', {
                                  defaultValue: 'Task {{name}}',
                                  name: taskDef.displayName,
                              })
                            : undefined
                    }
                    open={open}
                    maxWidth={'md'}
                    onClose={onClose}
                    loading={!task}
                    actions={({onClose, loading}) => {
                        return (
                            <>
                                <Button onClick={onClose} disabled={loading}>
                                    {t('dialog.close', 'Close')}
                                </Button>
                            </>
                        );
                    }}
                >
                    {task ? (
                        <>
                            <InfoRow
                                label={t('task.details.info.task', 'Task')}
                                value={task.task}
                                icon={<TaskIcon />}
                            />
                            <InfoRow
                                label={t(
                                    'task.details.info.payload',
                                    'Payload'
                                )}
                                value={
                                    <pre>
                                        {JSON.stringify(task.payload, null, 4)}
                                    </pre>
                                }
                                copyValue={JSON.stringify(task.payload)}
                                icon={<DataObjectIcon />}
                            />
                            <InfoRow
                                label={t(
                                    'task.details.info.createdAt',
                                    'Initiated At'
                                )}
                                value={task.createdAt}
                                copyValue={task.createdAt}
                                icon={<EventIcon />}
                            />
                            <InfoRow
                                label={t(
                                    'task.details.info.startedAt',
                                    'Started At'
                                )}
                                value={task.startedAt ?? '-'}
                                copyValue={task.startedAt}
                                icon={<EventIcon />}
                            />
                            <InfoRow
                                label={t(
                                    'task.details.info.endedAt',
                                    'Ended At'
                                )}
                                value={task.endedAt ?? '-'}
                                copyValue={task.endedAt}
                                icon={<EventIcon />}
                            />
                            <InfoRow
                                label={t('task.details.info.output', 'Output')}
                                value={task.output ?? '-'}
                                copyValue={task.output}
                                icon={<OutputIcon />}
                            />
                            <InfoRow
                                label={t(
                                    'task.details.info.progress',
                                    'Item processed'
                                )}
                                value={task.progress ?? '-'}
                                copyValue={task.progress}
                                icon={<NumbersIcon />}
                            />
                            <InfoRow
                                label={t(
                                    'task.details.info.itemTotal',
                                    'Item Total'
                                )}
                                value={task.itemTotal ?? '-'}
                                copyValue={task.itemTotal}
                                icon={<FunctionsIcon />}
                            />
                            <InfoRow
                                label={t(
                                    'task.details.info.duration',
                                    'Duration'
                                )}
                                value={
                                    task.endedAt
                                        ? formatDuration(
                                              moment(task.endedAt).diff(
                                                  task.startedAt
                                              ) / 1000
                                          )
                                        : '-'
                                }
                                icon={<TimerIcon />}
                            />
                            <InfoRow
                                label={t('task.details.info.owner', `Owner`)}
                                value={
                                    (task.owner as User).username ??
                                    (task.owner as User).id ??
                                    '-'
                                }
                                copyValue={(task.owner as User).id}
                                icon={<PersonIcon />}
                            />
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
