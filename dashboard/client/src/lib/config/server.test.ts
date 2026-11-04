import {describe, expect, it} from 'vitest';
import {getServerConfig} from './server';

describe('getServerConfig', () => {
    it('deduces the deployed applications from their API URL', () => {
        const config = getServerConfig(
            {logo: {src: 'https://cdn.test/logo.svg', style: 'margin: 4px'}},
            {
                DATABOX_API_URL: 'https://api-databox.test/',
                DATABOX_CLIENT_URL: 'https://databox.test',
                EXPOSE_CLIENT_URL: 'https://expose.test',
                PGADMIN_URL: 'https://pgadmin.test',
                MATOMO_URL: 'https://matomo.test',
                STACK_NAME: 'phrasea',
                STACK_VERSION: '1.2.3',
                DEV_MODE: 'true',
            }
        );

        expect(config.apps).toEqual({
            databox: {
                apiUrl: 'https://api-databox.test',
                clientUrl: 'https://databox.test',
            },
        });
        expect(config.tools).toEqual({
            pgadmin: 'https://pgadmin.test',
            matomo: 'https://matomo.test',
        });
        expect(config.stack).toEqual({name: 'phrasea', version: '1.2.3'});
        expect(config.devMode).toBe(true);
        // Matomo tracking needs a site too
        expect(config.analytics).toEqual({});
        expect(config.logo).toEqual({
            src: 'https://cdn.test/logo.svg',
            style: 'margin: 4px',
        });
        expect(config.keycloak.clientId).toBe('dashboard-app');
    });
});
