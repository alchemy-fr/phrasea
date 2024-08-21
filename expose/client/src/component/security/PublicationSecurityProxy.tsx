import React, {PropsWithChildren} from 'react';
import {Publication} from '../../types';
import {securityMethods} from './methods';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import {logPublicationView} from '../../lib/log';
import {keycloakClient} from '../../lib/api-client';
import {useTranslation} from 'react-i18next';

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
    const {t} = useTranslation();
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
                <p>
                    {t(
                        'publication.not_allowed',
                        `Sorry! You are not allowed to access this publication.`
                    )}
                </p>

                <button onClick={logout} className={'btn btn-sm btn-logout'}>
                    {t('publication.logout', `Logout`)}
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

    return <div>{t('publication_security_proxy.sorry_you_cannot_access_this_publication', `Sorry! You cannot access this publication.`)}</div>;
}
