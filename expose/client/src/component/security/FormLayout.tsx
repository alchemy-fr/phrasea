import React, {PropsWithChildren} from 'react';

type Props = PropsWithChildren<{}>;

export default function FormLayout({
    children,
}: Props) {
    return <>
        <div className="container">
            <div className="row">
                <div className="col-md-5 mx-auto">
                    <div className="form-container">
                        {children}
                    </div>
                </div>
            </div>
        </div>
    </>
}
