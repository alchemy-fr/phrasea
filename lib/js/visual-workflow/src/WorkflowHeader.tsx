import React, {MouseEventHandler} from 'react';
import HorizontalTable, {Cells} from "./Ui/HorizontalTable";
import {workflowStatuses} from "./status";
import DateValue from "./Ui/DateValue";
import {OnRefresh, Workflow} from "./types";
import DetailTitle from "./Ui/DetailTitle";
import JobData from "./Job/JobData";
import {SlArrowDown, SlArrowUp} from 'react-icons/sl';
import Button from "./Ui/Button";
import './style/WorkflowHeader.scss';
import {MdReplay} from "react-icons/md";

type Props = {
    workflow: Workflow;
    onRefreshWorkflow?: OnRefresh,
};

export default function WorkflowHeader({
    workflow,
    onRefreshWorkflow,
}: Props) {
    const [expanded, setExpanded] = React.useState(false);
    const [refreshing, setRefreshing] = React.useState(false);

    const toggleExpanded: MouseEventHandler<HTMLSpanElement> = (e => {
        e.stopPropagation();
        setExpanded(p => !p);
    });

    const values: Cells = [
        [`ID`, workflow.id],
        [`Name`, workflow.name],
        [`Event`, workflow.event ? <span
            onClick={toggleExpanded}
            style={{
                cursor: 'pointer',
            }}
        >
            {workflow.event.name}
        </span> : '-'],
        [`Status`, workflow.status ? workflowStatuses[workflow.status] : '-'],
        [`Duration`, workflow.duration ?? '-'],
        [`Started At`, <DateValue date={workflow.startedAt}/>],
    ];

    const Arrow = expanded ? SlArrowDown : SlArrowUp;

    return <div
        className={'workflow-header'}
    >
        <div style={{
            display: 'flex',
            flexDirection: 'row',
            alignItems: 'center'
        }}>
            <div style={{
                paddingRight: 15,
            }}>
                <Button
                    onClick={toggleExpanded}
                >
                    <Arrow/>
                </Button>
            </div>
            <div>
                <HorizontalTable
                    values={values}
                />
            </div>
            {onRefreshWorkflow && <div>
                <Button
                    disabled={refreshing}
                    loading={refreshing}
                    onClick={(e) => {
                        e.stopPropagation();

                        setRefreshing(true);
                        onRefreshWorkflow!().finally(() => {
                            setRefreshing(false);
                        });
                    }}
                    icon={MdReplay}
                >
                    Refresh
                </Button>
            </div>}
        </div>

        {expanded && <div>
            {Boolean(workflow.event) && <section className={'workflow-section'}>
                <DetailTitle>Event</DetailTitle>
                <div>{workflow.event!.name}</div>
                <JobData data={workflow.event!.inputs ?? {}}/>
            </section>}
            {Boolean(workflow.context) && <section className={'workflow-section'}>
                <DetailTitle>Context</DetailTitle>
                <JobData data={workflow.context!}/>
            </section>}
            {Boolean(workflow.outputs) && <section className={'workflow-section'}>
                <DetailTitle>Outputs</DetailTitle>
                <JobData data={workflow.outputs!}/>
            </section>}
        </div>}
    </div>
}
