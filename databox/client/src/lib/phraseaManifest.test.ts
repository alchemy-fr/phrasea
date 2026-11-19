import {describe, expect, it} from 'vitest';
import type {AppConfig} from '@/lib/config/types';
import {buildPhraseaManifest} from './phraseaManifest';

function config(autoConnectIdP?: string): AppConfig {
    return {
        apiUrl: 'https://api-databox.example.com',
        keycloak: {
            url: 'https://keycloak.example.com',
            realm: 'phrasea',
            clientId: 'databox-app',
            autoConnectIdP,
        },
    } as AppConfig;
}

describe('buildPhraseaManifest', () => {
    it('lists the Keycloak endpoints of the realm and the Databox API', () => {
        expect(buildPhraseaManifest(config())).toEqual({
            authUrl:
                'https://keycloak.example.com/realms/phrasea/protocol/openid-connect/auth',
            tokenUrl:
                'https://keycloak.example.com/realms/phrasea/protocol/openid-connect/token',
            userinfoUrl:
                'https://keycloak.example.com/realms/phrasea/protocol/openid-connect/userinfo',
            logoutUrl:
                'https://keycloak.example.com/realms/phrasea/protocol/openid-connect/logout',
            openidConfigurationUrl:
                'https://keycloak.example.com/realms/phrasea/.well-known/openid-configuration',
            databoxApiUrl: 'https://api-databox.example.com',
        });
    });

    it('hints the identity provider to connect to automatically', () => {
        expect(buildPhraseaManifest(config('my idp')).authUrl).toBe(
            'https://keycloak.example.com/realms/phrasea/protocol/openid-connect/auth?kc_idp_hint=my%20idp'
        );
    });
});
