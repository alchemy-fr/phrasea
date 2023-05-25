import React from 'react';
import {JobStatus, NodeData} from "../types";
import Modal from "../Modal";
import JobErrors from "./JobErrors";
import DetailTitle from "../Ui/DetailTitle";
import JobOutputs from "./JobOutputs";
import HorizontalTable, {Cells} from "../Ui/HorizontalTable";
import DateValue from "../Ui/DateValue";
import {jobStatuses} from "../status";
import Button from "../Ui/Button";
import {MdReplay} from "react-icons/md";

type Props = {
    job: NodeData;
};

export default function JobDetail({
    job,
}: Props) {
    const [rerunning, setRerunning] = React.useState(false);
    const values: Cells = [
        [`Status`, job.status ? jobStatuses[job.status] : '-'],
        [`Duration`, job.duration ?? '-'],
        [`Started At`, <DateValue date={job.startedAt}/>],
    ];

    if (job.status && ![
        JobStatus.Running,
        JobStatus.Triggered,
    ].includes(job.status) && job.onRerun) {
        values.push([``, <Button
            disabled={rerunning}
            loading={rerunning}
            variant={'primary'}
            onClick={(e) => {
                e.stopPropagation();

                setRerunning(true);
                job.onRerun!(job.id).finally(() => {
                    setRerunning(false);
                });
            }}
            icon={MdReplay}
        >
            Rerun
        </Button>]);
    }

    return <Modal>
        <HorizontalTable
            values={values}
        />
        {Boolean(job.outputs) && <section className={'workflow-section'}>
            <DetailTitle>Outputs</DetailTitle>
            <JobOutputs outputs={job.outputs!}/>
        </section>}
        {Boolean(job.errors?.length) && <section className={'workflow-section'}>
            <DetailTitle>Errors</DetailTitle>
            <JobErrors errors={job.errors!}/>
        </section>}
    </Modal>
}
