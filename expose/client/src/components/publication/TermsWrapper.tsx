import React, {PropsWithChildren} from 'react';
import {isTermsAccepted, setAcceptedTerms} from '../../lib/terms.ts';
import {Publication} from '../../types.ts';
import TermsDialog from './layouts/common/TermsDialog.tsx';

type Props = PropsWithChildren<{
    publication: Publication;
}>;

export default function TermsWrapper({publication, children}: Props) {
    const {terms} = publication;

    const [accepted, setAccepted] = React.useState(
        isTermsAccepted('p_' + publication.id)
    );

    const acceptTerms = () => {
        setAcceptedTerms('p_' + publication.id);
        setAccepted(true);
    };

    const displayPublication = !terms?.enabled || accepted;

    return (
        <>
            {terms?.enabled && (
                <TermsDialog
                    terms={publication.terms!}
                    accepted={accepted}
                    onAccept={acceptTerms}
                />
            )}
            {displayPublication ? (
                children
            ) : (
                <div
                    style={{
                        filter: 'blur(30px)',
                        pointerEvents: 'none',
                    }}
                >
                    {children}
                </div>
            )}
        </>
    );
}
