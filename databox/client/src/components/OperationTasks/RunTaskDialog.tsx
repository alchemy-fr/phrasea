import {Button} from '@mui/material';
import {useTasks} from './tasks/useTasks.ts';
import React from 'react';
import {AppDialog} from '@alchemy/phrasea-ui';
import {useTranslation} from 'react-i18next';
import RouteDialog from '../Dialog/RouteDialog.tsx';
import {useParams} from '@alchemy/navigation';
import {useFormSubmit} from '@alchemy/api';
import {postRunOperationTask} from '../../api/operationTask.ts';
import {useCloseModal} from '../Routing/ModalLink.tsx';

type Props = {};

export default function RunTaskDialog({}: Props) {
    const {t} = useTranslation();
    const {task} = useParams();
    const tasks = useTasks();
    const closeModal = useCloseModal();

    const taskO = tasks.find(t => t.name === task);
    const component = taskO?.component;
    const formId = 'run-task';

    const usedFormSubmit = useFormSubmit<any>({
        defaultValues: taskO?.defaultValues ?? {},
        onSubmit: async data => {
            return await postRunOperationTask({
                name: taskO!.name,
                payload: data,
            });
        },
        onSuccess: () => {
            closeModal();
        },
    });
    const {handleSubmit, submitting} = usedFormSubmit;

    return (
        <RouteDialog>
            {({open, onClose}) => (
                <AppDialog
                    title={t('run_task.title', {
                        defaultValue: 'Run Task: {{name}}',
                        name: taskO?.displayName,
                    })}
                    open={open}
                    loading={submitting}
                    disablePadding={true}
                    maxWidth={'md'}
                    onClose={onClose}
                    actions={({onClose, loading}) => {
                        return (
                            <>
                                <Button onClick={onClose} disabled={loading}>
                                    {t('dialog.close', 'Close')}
                                </Button>
                                <Button
                                    type={'submit'}
                                    form={formId}
                                    disabled={loading}
                                    loading={loading}
                                    variant={'contained'}
                                >
                                    {t('run_task.submit', 'Run')}
                                </Button>
                            </>
                        );
                    }}
                >
                    {component ? (
                        <form id={formId} onSubmit={handleSubmit}>
                            {React.createElement(component, {
                                usedFormSubmit,
                            })}
                        </form>
                    ) : (
                        <div>{t('run_task.not_found', 'No task found')}</div>
                    )}
                </AppDialog>
            )}
        </RouteDialog>
    );
}
