import {describe, expect, it} from 'vitest';
import {analyticsConfigFromEnv} from './config';

const env = {MATOMO_URL: 'https://matomo.test', MATOMO_SITE_ID: '3'};

describe('analyticsConfigFromEnv', () => {
    it('has no Matomo without its URL and site', () => {
        expect(analyticsConfigFromEnv({})).toEqual({});
        expect(
            analyticsConfigFromEnv({MATOMO_URL: 'https://matomo.test'})
        ).toEqual({});
        expect(analyticsConfigFromEnv({MATOMO_SITE_ID: '3'})).toEqual({});
    });

    it('asks for the cookie consent by default', () => {
        expect(analyticsConfigFromEnv(env)).toEqual({
            matomo: {
                baseUrl: 'https://matomo.test',
                siteId: '3',
                disableCookies: false,
                cookieConsent: true,
            },
        });
        expect(
            analyticsConfigFromEnv({...env, MATOMO_COOKIE_CONSENT: ''}).matomo
                ?.cookieConsent
        ).toBe(true);
        expect(
            analyticsConfigFromEnv({...env, MATOMO_COOKIE_CONSENT: 'off'})
                .matomo?.cookieConsent
        ).toBe(false);
    });

    it('reads the media plugin for a client playing media only', () => {
        const withPlugin = {...env, MATOMO_MEDIA_PLUGIN_ENABLED: 'yes'};

        expect(
            analyticsConfigFromEnv(withPlugin, {mediaPlugin: true}).matomo
                ?.mediaPluginEnabled
        ).toBe(true);
        expect(analyticsConfigFromEnv(withPlugin).matomo).not.toHaveProperty(
            'mediaPluginEnabled'
        );
        expect(
            analyticsConfigFromEnv(
                {...env, MATOMO_DISABLE_COOKIES: 'TRUE'},
                {mediaPlugin: true}
            ).matomo
        ).toMatchObject({mediaPluginEnabled: false, disableCookies: true});
    });
});
