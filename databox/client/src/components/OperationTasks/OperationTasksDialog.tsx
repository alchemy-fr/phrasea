import {
    Button,
    List,
    ListItem,
    ListItemButton,
    ListItemText,
} from '@mui/material';
import {useTasks} from './tasks/useTasks.ts';
import React from 'react';
import {AppDialog} from '@alchemy/phrasea-ui';
import {useTranslation} from 'react-i18next';
import RouteDialog from '../Dialog/RouteDialog.tsx';
import {useNavigateToModal} from '../Routing/ModalLink.tsx';
import {modalRoutes} from '../../routes.ts';

type Props = {};

export default function OperationTasksDialog({}: Props) {
    const {t} = useTranslation();
    const tasks = useTasks();
    const navigateToModal = useNavigateToModal();

    return (
        <RouteDialog>
            {({open, onClose}) => (
                <AppDialog
                    title={t('operation_tasks.title', 'Operation Tasks')}
                    open={open}
                    disablePadding={true}
                    maxWidth={'md'}
                    onClose={onClose}
                    actions={({onClose}) => {
                        return (
                            <Button onClick={onClose}>
                                {t('dialog.close', 'Close')}
                            </Button>
                        );
                    }}
                >
                    <List>
                        {tasks.map(task => (
                            <ListItem key={task.name} disablePadding>
                                <ListItemButton
                                    onClick={() => {
                                        navigateToModal(
                                            modalRoutes.operationTasks.routes
                                                .task,
                                            {
                                                task: task.name,
                                            }
                                        );
                                    }}
                                >
                                    <ListItemText
                                        primary={task.displayName}
                                        secondary={task.description}
                                    />
                                </ListItemButton>
                            </ListItem>
                        ))}
                    </List>
                </AppDialog>
            )}
        </RouteDialog>
    );
}
