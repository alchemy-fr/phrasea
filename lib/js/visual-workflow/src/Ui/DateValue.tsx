import React from 'react';
import moment from "moment/moment";

type Props = {
    date: string | undefined;
};

export default function DateValue({date}: Props) {
    const m = date ? moment(date) : undefined;

    return <span
        className={'workflow-date'}
        title={m?.format('LL LTS')}
    >
        {m ? m.fromNow() : '-'}
    </span>
}
