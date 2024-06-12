export type Workflow = {
    id: string;
    name: string;
    status: WorkflowStatus;
    duration?: string | undefined;
    stages: Stage[];
    startedAt: string;
    endedAt?: string | undefined;
    outputs?: Outputs;
    event?: WorkflowEvent | undefined;
    context?: WorkflowContext;
}

export type WorkflowEvent = {
    name: string;
    inputs?: Inputs;
}

export type Stage = {
    jobs: Job[];
}

export type JobError = string;

export type WorkflowContext = Record<string, any>;
export type Inputs = Record<string, any>;
export type Outputs = Record<string, any>;

export type Job = {
    id: string;
    name: string;
    status?: JobStatus | undefined;
    errors?: JobError[] | undefined;
    duration?: string;
    needs?: string[];
    if?: string | undefined;
    isDependency?: boolean;
    triggeredAt?: string | undefined;
    startedAt?: string | undefined;
    endedAt?: string | undefined;
    inputs?: Inputs;
    outputs?: Outputs;
}

export type NodeData = {
    onRerun: OnRerun | undefined;
} & Job;

export enum JobStatus {
    Triggered = 0,
    Success = 1,
    Failure = 2,
    Skipped = 3,
    Running = 4,
    Error = 5,
    Cancelled = 6,
}

export enum WorkflowStatus {
    Started = 0,
    Success = 1,
    Failure = 2,
    Cancelled = 3,
}

export type OnRerun = (jobId: string) => Promise<void>;
export type OnCancel = () => Promise<void>;
export type OnRefresh = () => Promise<void>;
