import React from "react";
import {Handle, Position} from "reactflow";
import {NodeProps} from "@reactflow/core/dist/esm/types/nodes";
import {NodeData} from "../types";
import JobStatusIndicator from "../JobStatusIndicator";
import JobDetail from "./JobDetail";

export default React.memo(({data, selected}: NodeProps<NodeData>) => {
    return <>
        {data.needs?.length ? <Handle
            type="target"
            position={Position.Left}
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
