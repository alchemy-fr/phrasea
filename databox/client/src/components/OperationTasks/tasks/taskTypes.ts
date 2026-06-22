import {UseFormSubmitReturn} from '@alchemy/api';
import {OperationTask} from '../../../api/types.ts';

type TaskPayload = Record<string, any>;

export type TaskComponentProps = {
    usedFormSubmit: UseFormSubmitReturn<TaskPayload, OperationTask>;
};
