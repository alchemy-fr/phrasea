import React from "react";
import {Handle, NodeProps, Position} from "reactflow";
import {NodeData} from "../types";
import JobStatusIndicator from "../JobStatusIndicator";
import JobDetail from "./JobDetail";

export default React.memo(({data, selected}: NodeProps<NodeData>) => {
    return <>
        {data.needs?.length ? <Handle
            type="target"
            position={Position.Left}
            className={data.disabled ? 'job-disabled' : undefined}
        /> : ''}
        <div
            className={'job-content'}
            title={data.name}
        >
            <div className={'job-status'}>
                <JobStatusIndicator
                    status={data.status}
                />
            </div>
            <div className={'job-name'}>
                {data.name}
            </div>
            {data.duration && <div className={'job-duration'}>{data.duration}</div>}
            {data.disabled && data.disabledReason ? <div
                className={'job-disabled-reason'}
                title={data.disabledReason}
            >🚫</div> : ''}
        </div>
        {data.isDependency && <Handle
            type="source"
            position={Position.Right}
        />}
        {selected && <JobDetail
            job={data}
        />}
    </>
});
