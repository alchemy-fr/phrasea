import {initApp} from '@alchemy/phrasea-framework';

const {apiClient, oauthClient, keycloakClient, matomo, config} = initApp({
    appName: 'uploader',
});

export {apiClient, oauthClient, keycloakClient, matomo, config};
