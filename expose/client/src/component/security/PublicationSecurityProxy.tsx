import React, {PropsWithChildren} from 'react';
import {Publication} from '../../types';
import {securityMethods} from './methods';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import {logPublicationView} from '../../lib/log';
import {keycloakClient} from '../../lib/api-client';

type Props = PropsWithChildren<{
    publication: Publication | undefined;
    reload: () => void;
    logPublicationView?: boolean;
}>;

export default function PublicationSecurityProxy({
    children,
    publication,
    reload,
    logPublicationView: log,
}: Props) {
    React.useEffect(() => {
        if (log && publication && publication.authorized) {
            logPublicationView(publication!.id);
        }
    }, [publication?.id, log]);

    const logout = () => {
        keycloakClient.logout();
    };

    if (!publication) {
        return <FullPageLoader backdrop={false} />;
    }

    const {
        authorized,
        securityContainerId,
        authorizationError,
        securityMethod,
    } = publication!;

    if (authorized) {
        return children as JSX.Element;
    }

    if (authorizationError === 'not_allowed') {
        return (
            <div
                style={{
                    padding: 10,
                }}
            >
                <p>Sorry! You are not allowed to access this publication.</p>

                <button onClick={logout} className={'btn btn-sm btn-logout'}>
                    Logout
                </button>
            </div>
        );
    }

    if (securityMethods[securityMethod]) {
        return React.createElement(securityMethods[securityMethod], {
            error: authorizationError,
            onAuthorization: reload,
            securityContainerId,
        });
    }

    return <div>Sorry! You cannot access this publication.</div>;
}
