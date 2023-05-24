import React from 'react';
import {Outputs} from "../types";

type Props = {
    outputs: Outputs;
};

export default function JobOutputs({
    outputs,
}: Props) {
    return <pre
        className={'job-outputs'}
    >
       {JSON.stringify(outputs, null, 4)}
    </pre>
}
