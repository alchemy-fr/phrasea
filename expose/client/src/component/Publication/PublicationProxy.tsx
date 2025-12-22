import {AuthorizationError, Publication} from '../../types.ts';
import PublicationError from './PublicationError.tsx';
import {securityMethods} from '../security/methods.tsx';
import React, {PropsWithChildren} from 'react';
import ErrorPage from '../ErrorPage.tsx';
import {useTranslation} from 'react-i18next';
import {Alert} from '@mui/material';
import TermsWrapper from './TermsWrapper.tsx';
import {FullPageLoader} from '@alchemy/phrasea-ui';

type Props = PropsWithChildren<{
    publication: Publication | undefined;
    errorCode?: number;
    loading?: boolean;
    load: () => Promise<void>;
}>;

export default function PublicationProxy({
    publication,
    children,
    errorCode,
    load,
    loading,
}: Props) {
    const {t} = useTranslation();
    if (errorCode) {
        return <PublicationError errorCode={errorCode} />;
    }

    if (!publication) {
        return <FullPageLoader backdrop={false} />;
    }

    const {
        authorized,
        securityContainerId,
        authorizationError,
        securityMethod,
        enabled,
    } = publication!;

    if (!authorized) {
        if (securityMethods[securityMethod]) {
            return React.createElement(securityMethods[securityMethod], {
                error: authorizationError,
                onAuthorization: load,
                securityContainerId,
                loading: loading ?? false,
            });
        }
    }

    if (!authorized || authorizationError === AuthorizationError.NotAllowed) {
        return (
            <ErrorPage
                title={t(
                    'publication.not_allowed',
                    `Sorry! You are not allowed to access this publication.`
                )}
            />
        );
    }

    return (
        <>
            {publication.cssLink ? (
                <link
                    rel="stylesheet"
                    type="text/css"
                    href={publication.cssLink}
                />
            ) : null}
            {!enabled && (
                <Alert>
                    {t(
                        'publication.disabled',
                        'This publication is currently disabled. Only administrators can see it.'
                    )}
                </Alert>
            )}
            <TermsWrapper publication={publication}>{children}</TermsWrapper>
        </>
    );
}
