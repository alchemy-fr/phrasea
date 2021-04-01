import React, {PropsWithChildren} from 'react';

type Props = {};

export default function Container({children}: PropsWithChildren<Props>) {
    return (
        <div className="container">
            {children}
        </div>
    );
}
