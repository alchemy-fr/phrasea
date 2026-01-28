import {initApp} from '@alchemy/phrasea-framework';
const {apiClient, oauthClient, keycloakClient, matomo, config} = initApp({
    appName: 'databox',
});

export {apiClient, oauthClient, keycloakClient, matomo, config};
