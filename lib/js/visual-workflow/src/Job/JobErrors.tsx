import React from 'react';
import {JobError} from "../types";

type Props = {
    errors: JobError[];
};

export default function JobErrors({
    errors,
}: Props) {
    return <ul
        className={'job-errors'}
    >
        {errors.map((e, i) => <li
            key={i}
        >
            {e}
        </li>)}
    </ul>
}
