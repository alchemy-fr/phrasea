import {useOneTimeToken} from '@alchemy/react-auth';
import {openPopup} from '@alchemy/core/src/popup';
import {getIntegrationTokens} from '../../api/integrations.ts';
import {WorkspaceIntegration} from '../../types.ts';
import React from 'react';
import {useTranslation} from 'react-i18next';
import {apiClient, config} from '../../init.ts';

type Props = {
    integration: WorkspaceIntegration;
};

export function useIntegrationAuth({integration}: Props) {
    const {t} = useTranslation();
    const {loading, getToken} = useOneTimeToken(apiClient);
    const [tokens, setTokens] = React.useState(integration.tokens);
    const [loadingTokens, setLoadingTokens] = React.useState(false);

    const requestAuth = async () => {
        const token = await getToken();

        const win = openPopup({
            url: `${config.baseUrl}/integrations/${
                integration.id
            }/auth?token=${encodeURIComponent(token)}`,
            title: t('use_integration_auth.auth', `Auth`),
        });

        const handleClosed = async () => {
            timer && clearInterval(timer);

            setLoadingTokens(true);
            try {
                setTokens((await getIntegrationTokens(integration.id)).result);
            } finally {
                setLoadingTokens(false);
            }
        };

        const timer = setInterval(() => {
            if (win.closed) {
                handleClosed();
            }
        }, 1000);

        try {
            win.addEventListener('close', handleClosed);
        } catch (e: any) {
            // Ignore cross origin security error
        }
    };

    return {
        loading: loading || loadingTokens,
        requestAuth,
        hasValidToken: tokens.some(token => !token.expired),
    };
}
