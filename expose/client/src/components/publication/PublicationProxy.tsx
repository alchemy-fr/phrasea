import {AuthorizationError, Publication} from '../../types.ts';
import PublicationError from './PublicationError.tsx';
import {securityMethods} from '../security/methods.tsx';
import React, {PropsWithChildren} from 'react';
import ErrorPage from '../ErrorPage.tsx';
import {useTranslation} from 'react-i18next';
import TermsWrapper from './TermsWrapper.tsx';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import PublicationHeader from './layouts/common/PublicationHeader.tsx';

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
            <TermsWrapper publication={publication}>
                <PublicationHeader publication={publication}>
                    {children}
                </PublicationHeader>
            </TermsWrapper>
        </>
    );
}
