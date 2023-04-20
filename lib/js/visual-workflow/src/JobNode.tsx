import React from "react";
import {Handle, Position} from "reactflow";
import {NodeProps} from "@reactflow/core/dist/esm/types/nodes";
import {Job} from "./types";
import JobStatusIndicator from "./JobStatusIndicator";

export default React.memo(({data}: NodeProps<Job>) => {
    return <>
        {data.needs?.length && <Handle
            type="target"
            position={Position.Left}
        />}
        <div className={'job-content'}>
            <div className={'job-status'}>
                <JobStatusIndicator
                    status={data.status}
                />
            </div>
            <div className={'job-name'}>
                {data.name}
            </div>
            {data.duration && <small>{data.duration}</small>}
        </div>
        {data.isDependency && <Handle
            type="source"
            position={Position.Right}
        />}
    </>
});