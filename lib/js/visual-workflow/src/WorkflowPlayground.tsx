import React, {PropsWithChildren} from 'react';

type Props = PropsWithChildren<{} & React.HTMLProps<HTMLDivElement>>;

export default function WorkflowPlayground({children, ...rest}: Props) {
    return (
        <div {...rest} className={'workflow-playground'}>
            {children}
        </div>
    );
}
