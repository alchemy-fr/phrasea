import {JobStatus} from "./types";
import './style/status-indicator.scss';

type Props = {
    status: JobStatus | undefined;
};

export default function JobStatusIndicator({
    status
}: Props) {
    const className = {
        [JobStatus.Failure]: 'workflow-failure',
        [JobStatus.Success]: 'workflow-success',
        [JobStatus.Running]: 'workflow-running',
        [JobStatus.Triggered]: 'workflow-triggered',
        [JobStatus.Skipped]: 'workflow-skipped',
        [JobStatus.Error]: 'workflow-error',
    }

    const pulse = status === JobStatus.Running;

    return <div
        className={`workflow-status ${undefined !== status ? className[status] : 'none'} ${pulse ? 'workflow-pulse' : ''}`}
    />
}
