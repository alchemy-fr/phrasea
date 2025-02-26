import {PropsWithChildren} from 'react';

type Props = PropsWithChildren<{
    inline?: boolean;
}>;

export default function DetailTitle({inline, children}: Props) {
    return (
        <div className={`detail-title ${inline ? ' inline' : ''}`}>
            {children}
        </div>
    );
}
