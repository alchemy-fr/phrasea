import config from '../../config';
import {keycloakClient} from '../../lib/api-client';
import FormLayout from './FormLayout';
import {useAuth, useKeycloakUrls} from '@alchemy/react-auth';
import {getCurrentPath} from '@alchemy/navigation';
import React from 'react';

type Props = {};

export default function AuthenticationMethod({}: Props) {
    const {setRedirectPath} = useAuth();

    const {getLoginUrl} = useKeycloakUrls({
        keycloakClient: keycloakClient,
        autoConnectIdP: config.autoConnectIdP,
    })

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
                    <h3>This publication requires authentication.</h3>
                    <a
                        className={'btn btn-primary'}
                        onClick={onConnect}
                        href={getLoginUrl()}
                    >
                        Login
                    </a>
                </div>
            </FormLayout>
        </div>
    );
}
