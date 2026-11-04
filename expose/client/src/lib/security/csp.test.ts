import {describe, expect, it} from 'vitest';
import {buildContentSecurityPolicy} from './csp';

describe('buildContentSecurityPolicy', () => {
    it('only allows the same origin by default', () => {
        expect(buildContentSecurityPolicy({})).toBe(
            "frame-src 'self'; frame-ancestors 'self'"
        );
    });

    it('allows Keycloak, the dashboard and the configured ancestors', () => {
        expect(
            buildContentSecurityPolicy({
                KEYCLOAK_URL: 'https://keycloak.test/auth',
                DASHBOARD_CLIENT_URL: 'https://dashboard.test',
                ALLOWED_FRAME_ANCESTORS: ' https://a.test  https://b.test ',
            })
        ).toBe(
            "frame-src 'self' https://keycloak.test https://dashboard.test; frame-ancestors 'self' https://a.test https://b.test"
        );
    });
});
