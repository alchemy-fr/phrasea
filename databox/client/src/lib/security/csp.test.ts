import {describe, expect, it} from 'vitest';
import {buildCsp} from '@alchemy/next-core/security/csp';
import {contentSecuritySources} from './csp';

function policy(env: Record<string, string | undefined>): string {
    return buildCsp({nonce: 'n', ...contentSecuritySources(env)});
}

describe('contentSecuritySources', () => {
    it('only allows the same origin by default, and any embedding site', () => {
        const p = policy({});

        expect(p).toContain("connect-src 'self';");
        expect(p).toContain("frame-src 'self';");
        expect(p).not.toContain('frame-ancestors');
    });

    it('lets the pages talk to the API, the realtime server and the buckets', () => {
        const p = policy({
            DATABOX_API_URL: 'https://api-databox.test/',
            KEYCLOAK_URL: 'https://keycloak.test/auth',
            SOCKUDO_HOST: 'ws.test:8443',
            CONFIGURATOR_S3_ENDPOINT: 'https://minio.test',
            CONFIGURATOR_S3_USE_PATH_STYLE_ENDPOINT: 'true',
            CLOUD_FRONT_URL: 'https://cdn.test',
            DASHBOARD_CLIENT_URL: 'https://dashboard.test',
        });

        expect(p).toContain(
            "connect-src 'self' https://keycloak.test https://minio.test https://cdn.test https://api-databox.test wss://ws.test:8443;"
        );
        expect(p).toContain(
            "img-src 'self' data: blob: https://minio.test https://cdn.test https://api-databox.test;"
        );
        expect(p).toContain(
            "frame-src 'self' https://keycloak.test https://dashboard.test;"
        );
    });

    it('allows Mapbox only when a token is set', () => {
        expect(policy({MAPBOX_TOKEN: 'define-me'})).not.toContain('mapbox');
        expect(policy({MAPBOX_TOKEN: 'pk.x'})).toContain(
            "connect-src 'self' https://api.mapbox.com https://*.tiles.mapbox.com https://events.mapbox.com;"
        );
    });
});
