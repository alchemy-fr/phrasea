import React, {PropsWithChildren} from 'react';
import {isTermsAccepted, setAcceptedTerms} from '../../lib/credential.ts';
import {Publication} from '../../types.ts';
import TermsDialog from './TermsDialog.tsx';

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

    return (
        <>
            {terms?.enabled && (
                <TermsDialog
                    terms={publication.terms!}
                    accepted={accepted}
                    onAccept={acceptTerms}
                />
            )}
            {children}
        </>
    );
}
