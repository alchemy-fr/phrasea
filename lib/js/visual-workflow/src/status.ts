import {JobStatus, WorkflowStatus} from './types';

export const jobStatuses = {
    [JobStatus.Triggered]: 'Triggered',
    [JobStatus.Success]: 'Success',
    [JobStatus.Failure]: 'Failure',
    [JobStatus.Skipped]: 'Skipped',
    [JobStatus.Running]: 'Running',
    [JobStatus.Error]: 'Error',
    [JobStatus.Cancelled]: 'Cancelled',
};

export const workflowStatuses = {
    [WorkflowStatus.Started]: 'Started',
    [WorkflowStatus.Success]: 'Success',
    [WorkflowStatus.Failure]: 'Failure',
    [WorkflowStatus.Cancelled]: 'Cancelled',
};
