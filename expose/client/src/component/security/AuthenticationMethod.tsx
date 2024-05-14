import config from '../../config';
import {keycloakClient} from '../../lib/api-client';
import FormLayout from './FormLayout';
import {useAuth, useKeycloakUrls} from '@alchemy/react-auth';
import {getCurrentPath} from '@alchemy/navigation';
import {useTranslation} from 'react-i18next';
import React from 'react';

type Props = {};

export default function AuthenticationMethod({}: Props) {
    const {setRedirectPath} = useAuth();
    const {t} = useTranslation();

    const {getLoginUrl} = useKeycloakUrls({
        keycloakClient: keycloakClient,
        autoConnectIdP: config.autoConnectIdP,
    });

    const onConnect = React.useCallback(() => {
        setRedirectPath && setRedirectPath(getCurrentPath());
    }, []);

    return (
        <div className={'container'}>
            <FormLayout>
                <div
                    style={{
                        textAlign: 'center',
                    }}
                >
                    <h3>{t('publication.auth_required.title', `This publication requires authentication.`)}</h3>
                    <a
                        className={'btn btn-primary'}
                        onClick={onConnect}
                        href={getLoginUrl()}
                    >
                        {t('publication.auth_required.sign_in', `Sign In`)}
                    </a>
                </div>
            </FormLayout>
        </div>
    );
}
