import React from 'react';
import {JobStatus} from "./types";
import './style/status-indicator.scss';

type Props = {
    status: JobStatus | undefined;
};

export default function JobStatusIndicator({
    status
}: Props) {
    const className = {
        [JobStatus.Failure]: 'failure',
        [JobStatus.Success]: 'success',
        [JobStatus.Running]: 'running',
        [JobStatus.Triggered]: 'triggered',
        [JobStatus.Skipped]: 'skipped',
    }

    const pulse = status === JobStatus.Running;

    return <div
        className={`status ${undefined !== status ? className[status] : 'none'} ${pulse ? 'pulse' : ''}`}
    />
}
