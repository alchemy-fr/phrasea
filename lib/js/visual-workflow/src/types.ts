export type Workflow = {
    id: string;
    name: string;
    stages: Stage[];
}

export type Stage = {
    jobs: Job[];
}

export type Job = {
    id: string;
    name: string;
    status?: JobStatus | undefined;
    duration?: string;
    needs?: string[];
    isDependency?: boolean;
}

export enum JobStatus {
    Triggered = 0,
    Success = 1,
    Failure = 2,
    Skipped = 3,
    Running = 4,
}
