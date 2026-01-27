import React from 'react';
import {JobStatus, NodeData} from '../types';
import Modal from '../Modal';
import JobErrors from './JobErrors';
import DetailTitle from '../Ui/DetailTitle';
import JobData from './JobData';
import HorizontalTable, {Cells} from '../Ui/HorizontalTable';
import DateValue from '../Ui/DateValue';
import {jobStatuses} from '../status';
import ReplayIcon from '@mui/icons-material/Replay';
import {Button} from '@mui/material';

type Props = {
    job: NodeData;
};

export default function JobDetail({job}: Props) {
    const [rerunning, setRerunning] = React.useState(false);
    const values: Cells = [
        [`Status`, undefined !== job.status ? jobStatuses[job.status] : '-'],
        [`Duration`, job.duration ?? '-'],
        [`#`, (job.number ?? '-').toString()],
        [`Started At`, <DateValue date={job.startedAt} />],
    ];

    if (
        job.status &&
        ![JobStatus.Running, JobStatus.Triggered].includes(job.status) &&
        job.onRerun
    ) {
        values.push([
            ``,
            <Button
                disabled={rerunning}
                loading={rerunning}
                onClick={() => {
                    setRerunning(true);
                    job.onRerun!(job.jobId).finally(() => {
                        setRerunning(false);
                    });
                }}
                startIcon={<ReplayIcon />}
            >
                Rerun
            </Button>,
        ]);
    }

    return (
        <Modal>
            <HorizontalTable values={values} />
            {Boolean(job.if) && (
                <section className={'workflow-section'}>
                    <DetailTitle inline>if</DetailTitle>
                    <pre style={{display: 'inline-block'}}>{job.if}</pre>
                </section>
            )}
            {Boolean(job.inputs) && (
                <section className={'workflow-section'}>
                    <DetailTitle>Inputs</DetailTitle>
                    <JobData data={job.inputs!} />
                </section>
            )}
            {Boolean(job.outputs) && (
                <section className={'workflow-section'}>
                    <DetailTitle>Outputs</DetailTitle>
                    <JobData data={job.outputs!} />
                </section>
            )}
            {Boolean(job.errors?.length) && (
                <section className={'workflow-section'}>
                    <DetailTitle>Errors</DetailTitle>
                    <JobErrors errors={job.errors!} />
                </section>
            )}
        </Modal>
    );
}
