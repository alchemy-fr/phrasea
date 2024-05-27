import apiClient from "../../api/api-client.ts";
import {useOneTimeToken} from '@alchemy/react-auth';
import {openPopup} from '@alchemy/core/src/popup';
import config from "../../config.ts";
import {getIntegrationToken} from "../../api/integrations.ts";

type Props = {
    integrationId: string;
};

export function useIntegrationAuth({
    integrationId,
}: Props) {
    const {loading, getToken} = useOneTimeToken(apiClient);

    const requestAuth = async () => {
        const token = await getToken();

        const win = openPopup({
            url: `${config.baseUrl}/integrations/${integrationId}/auth?token=${encodeURIComponent(token)}`,
            title: 'Auth'
        });

        const handleClosed = () => {
            timer && clearInterval(timer);
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
    }

    return {
        loading,
        requestAuth,
    };
}
