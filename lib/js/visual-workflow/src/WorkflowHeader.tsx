import React, {MouseEventHandler} from 'react';
import HorizontalTable, {Cells} from "./Ui/HorizontalTable";
import {workflowStatuses} from "./status";
import DateValue from "./Ui/DateValue";
import {OnCancel, OnRefresh, Workflow, WorkflowStatus} from "./types";
import DetailTitle from "./Ui/DetailTitle";
import JobData from "./Job/JobData";
import ArrowDropUpIcon from '@mui/icons-material/ArrowDropUp';
import ArrowDropDownIcon from '@mui/icons-material/ArrowDropDown';
import './style/WorkflowHeader.scss';
import ReplayIcon from '@mui/icons-material/Replay';
import CancelIcon from '@mui/icons-material/Cancel';
import {IconButton} from "@mui/material";
import {LoadingButton} from "@mui/lab";

type Props = {
    workflow: Workflow;
    onRefreshWorkflow?: OnRefresh,
    onCancel?: OnCancel,
};

export default function WorkflowHeader({
    workflow,
    onRefreshWorkflow,
    onCancel,
}: Props) {
    const [expanded, setExpanded] = React.useState(false);
    const [refreshing, setRefreshing] = React.useState(false);
    const [cancelling, setCancelling] = React.useState(false);

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

    const Arrow = expanded ? ArrowDropDownIcon : ArrowDropUpIcon;

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
                <IconButton
                    onClick={toggleExpanded}
                >
                    <Arrow/>
                </IconButton>
            </div>
            <div>
                <HorizontalTable
                    values={values}
                />
            </div>
            <div>
                {onRefreshWorkflow && <LoadingButton
                    disabled={refreshing}
                    loading={refreshing}
                    color={'primary'}
                    onClick={() => {
                        setRefreshing(true);
                        onRefreshWorkflow!().finally(() => {
                            setRefreshing(false);
                        });
                    }}
                    startIcon={<ReplayIcon/>}
                >
                    Refresh
                </LoadingButton>}
                {onCancel && workflow.status !== WorkflowStatus.Cancelled && <LoadingButton
                    disabled={cancelling}
                    loading={cancelling}
                    color={'warning'}
                    sx={{
                        ml: 1,
                    }}
                    onClick={(e) => {
                        e.stopPropagation();

                        setCancelling(true);
                        onCancel!().finally(() => {
                            setCancelling(false);
                        });
                    }}
                    startIcon={<CancelIcon/>}
                >
                    Cancel
                </LoadingButton>}
            </div>
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
