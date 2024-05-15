import config from '../../config';
import {keycloakClient, oauthClient} from '../../lib/api-client';
import FormLayout from './FormLayout';
import {useAuth, useKeycloakUrls} from '@alchemy/react-auth';
import {getCurrentPath, getRelativeUrl} from '@alchemy/navigation';
import {useTranslation} from 'react-i18next';
import React from 'react';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import lockImg from '../../images/lock.svg';

type Props = {};

export default function AuthenticationMethod({}: Props) {
    const {setRedirectPath} = useAuth();
    const {t} = useTranslation();

    const {getLoginUrl} = useKeycloakUrls({
        keycloakClient: keycloakClient,
        autoConnectIdP: config.autoConnectIdP,
    });

    const searchParams = new URLSearchParams(window.location.search);
    const shouldRedirect = !oauthClient.sessionHasExpired && !searchParams.has('logout');

    React.useEffect(() => {
        if (shouldRedirect) {
            document.location.href = getLoginUrl();
        }
    }, [shouldRedirect]);

    const onConnect = React.useCallback(() => {
        setRedirectPath && setRedirectPath(getCurrentPath());
    }, []);

    if (shouldRedirect) {
        return <FullPageLoader/>
    }

    const redirectUri = new URL(document.location.href);
    redirectUri.searchParams.delete('logout');

    return (
        <div className={'container'}>
            <FormLayout>
                <div
                    style={{
                        textAlign: 'center',
                    }}
                >
                    <img
                        style={{
                            width: 100,
                            height: 100,
                            margin: `30px 0`,
                        }}
                        src={lockImg} alt="Lock"/>
                    <h3>{t('publication.auth_required.title', `This publication requires authentication.`)}</h3>
                    <a
                        style={{
                            margin: `30px 0`,
                        }}
                        className={'btn btn-primary'}
                        onClick={onConnect}
                        href={getLoginUrl(getRelativeUrl(redirectUri.toString()))}
                    >
                        {t('publication.auth_required.sign_in', `Sign In`)}
                    </a>
                </div>
            </FormLayout>
        </div>
    );
}
