import {config, keycloakClient, oauthClient} from '../../init.ts';
import FormLayout from './FormLayout';
import {useKeycloakUrls} from '@alchemy/react-auth';
import {AuthConstant, inIframe, openLoginWindow} from '@alchemy/auth';
import {getRelativeUrl} from '@alchemy/navigation';
import {useTranslation} from 'react-i18next';
import React from 'react';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import lockImg from '../../images/lock.svg';

type Props = {};

export default function AuthenticationMethod({}: Props) {
    const {t} = useTranslation();

    const {getLoginUrl} = useKeycloakUrls({
        keycloakClient: keycloakClient,
        autoConnectIdP: config.autoConnectIdP,
    });

    const currentLocation = new URL(document.location.href);
    const hasLoggedOut = currentLocation.searchParams.has(
        AuthConstant.LoggedOutParam
    );

    let redirectUri: string | undefined;

    if (hasLoggedOut) {
        const r = new URL(document.location.href);
        r.searchParams.delete(AuthConstant.LoggedOutParam);
        redirectUri = getRelativeUrl(r.toString());
    }

    const shouldRedirect = !oauthClient.sessionHasExpired && !hasLoggedOut;

    const isInIframe = inIframe();
    const loginUrl = getLoginUrl(redirectUri);

    React.useEffect(() => {
        if (shouldRedirect) {
            document.location.href = loginUrl;
        }
    }, [shouldRedirect, loginUrl]);

    const onConnect = React.useCallback(() => {
        if (isInIframe) {
            openLoginWindow(loginUrl);
        }
    }, [isInIframe, loginUrl]);

    if (shouldRedirect) {
        return <FullPageLoader />;
    }

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
                        src={lockImg}
                        alt="Lock"
                    />
                    <h3>
                        {t(
                            'publication.auth_required.title',
                            `This publication requires authentication.`
                        )}
                    </h3>
                    <a
                        style={{
                            margin: `30px 0`,
                        }}
                        className={'btn btn-primary'}
                        onClick={onConnect}
                        href={isInIframe ? '#' : loginUrl}
                    >
                        {t('publication.auth_required.sign_in', `Sign In`)}
                    </a>
                </div>
            </FormLayout>
        </div>
    );
}
