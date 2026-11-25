import {envBool} from '../config/env';
import type {MatomoConfig} from './matomo';

/** Matomo as configured by the environment of a client */
export type MatomoClientConfig = MatomoConfig & {
    /** `MATOMO_DISABLE_COOKIES`: tracks visitors without any cookie */
    disableCookies: boolean;
    /**
     * `MATOMO_COOKIE_CONSENT`: asks the visitor before setting the Matomo
     * cookies (default on)
     */
    cookieConsent: boolean;
};

export type AnalyticsConfig = {
    /** Set when `MATOMO_URL` and `MATOMO_SITE_ID` are */
    matomo?: MatomoClientConfig;
};

type AnalyticsOptions = {
    /** The client plays media: `MATOMO_MEDIA_PLUGIN_ENABLED` is read */
    mediaPlugin?: boolean;
};

/** The analytics of a client from the `MATOMO_*` environment variables */
export function analyticsConfigFromEnv(
    env: Record<string, string | undefined>,
    {mediaPlugin = false}: AnalyticsOptions = {}
): AnalyticsConfig {
    const baseUrl = env.MATOMO_URL;
    const siteId = env.MATOMO_SITE_ID;
    if (!baseUrl || !siteId) {
        return {};
    }

    return {
        matomo: {
            baseUrl,
            siteId,
            ...(mediaPlugin
                ? {mediaPluginEnabled: envBool(env.MATOMO_MEDIA_PLUGIN_ENABLED)}
                : {}),
            disableCookies: envBool(env.MATOMO_DISABLE_COOKIES),
            cookieConsent: envBool(env.MATOMO_COOKIE_CONSENT, true),
        },
    };
}
