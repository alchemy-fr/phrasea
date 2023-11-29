import {PropsWithChildren} from 'react';

type Props = PropsWithChildren<{}>;

export default function Modal({children}: Props) {
    return <div
        className={'workflow-modal'}
    >
        {children}
    </div>
}
