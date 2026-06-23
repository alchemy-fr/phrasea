import {CircularProgressWithLabel} from '@alchemy/phrasea-ui';
import {OperationTask, OperationTaskStatus} from '../../api/types.ts';
import {Box, ListItem, ListItemButton, ListItemText} from '@mui/material';
import moment from 'moment';
import ErrorIcon from '@mui/icons-material/Error';
import PendingIcon from '@mui/icons-material/Pending';
import CancelIcon from '@mui/icons-material/Cancel';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import {Trans} from 'react-i18next';
import {User} from '../../types.ts';
import {useNavigateToModal} from '../Routing/ModalLink.tsx';
import {modalRoutes} from '../../routes.ts';
import {formatDuration} from '../../lib/duration.ts';
import TimerIcon from '@mui/icons-material/Timer';
import {useTranslation} from 'react-i18next';
import HourglassBottomIcon from '@mui/icons-material/HourglassBottom';
import React from 'react';

type Props = {
    task: OperationTask;
    title: string;
};

export default function TaskCard({task, title}: Props) {
    const navigateToModal = useNavigateToModal();
    const {t} = useTranslation();

    return (
        <>
            <ListItem
                disablePadding={true}
                key={task.id}
                sx={theme => ({
                    borderBottom: `1px solid ${theme.palette.divider}`,
                })}
            >
                <ListItemButton
                    onClick={() => {
                        navigateToModal(
                            modalRoutes.operationTasks.routes.taskDetails,
                            {
                                id: task.id,
                            }
                        );
                    }}
                >
                    <Box
                        sx={{
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            gap: 2,
                            width: '100%',
                        }}
                    >
                        <div
                            style={{
                                minWidth: 70,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                            }}
                        >
                            {task.status === OperationTaskStatus.InProgress ? (
                                <CircularProgressWithLabel
                                    size={50}
                                    color={'info'}
                                    title={task.remaining}
                                    variant={'determinate'}
                                    value={task.progression ?? 0}
                                />
                            ) : null}
                            {task.status === OperationTaskStatus.Failed ? (
                                <ErrorIcon color={'error'} fontSize={'large'} />
                            ) : null}
                            {task.status === OperationTaskStatus.Completed ? (
                                <CheckCircleIcon
                                    color={'success'}
                                    fontSize={'large'}
                                />
                            ) : null}
                            {task.status === OperationTaskStatus.Pending ? (
                                <PendingIcon
                                    color={'info'}
                                    fontSize={'large'}
                                />
                            ) : null}
                            {task.status === OperationTaskStatus.Cancelled ? (
                                <CancelIcon
                                    color={'disabled'}
                                    fontSize={'large'}
                                />
                            ) : null}
                        </div>
                        <div
                            style={{
                                flexGrow: 1,
                            }}
                        >
                            <ListItemText
                                primary={title}
                                primaryTypographyProps={{
                                    sx: {
                                        fontSize: 16,
                                        fontWeight: 700,
                                    },
                                }}
                                secondary={
                                    <>
                                        <Trans
                                            i18nKey={'taskCard.runBy'}
                                            defaults={`<strong>{{task}}</strong> run by <strong>{{owner}}</strong>`}
                                            values={{
                                                owner: (task.owner as User)
                                                    .username,
                                                task: task.task,
                                            }}
                                        />
                                    </>
                                }
                            />
                        </div>
                        <div
                            style={{
                                textAlign: 'right',
                            }}
                        >
                            <div>
                                {moment(
                                    task.startedAt ?? task.createdAt
                                ).fromNow()}
                            </div>
                            {task.endedAt ? (
                                <div>
                                    <TimerIcon
                                        fontSize={'small'}
                                        sx={{
                                            mr: 1,
                                        }}
                                    />
                                    {formatDuration(
                                        moment(task.endedAt).diff(
                                            task.startedAt
                                        ) / 1000
                                    )}
                                </div>
                            ) : null}
                            {task.status === OperationTaskStatus.InProgress && (
                                <div>
                                    <HourglassBottomIcon
                                        fontSize={'small'}
                                        sx={{
                                            mr: 1,
                                        }}
                                    />
                                    {t('task.card.remaining', 'Remaining')}
                                    {task.remaining}
                                </div>
                            )}
                        </div>
                    </Box>
                </ListItemButton>
            </ListItem>
        </>
    );
}
