import {createInstance} from '@jonkoops/matomo-tracker-react'
import config from "./config";

const analytics = config.analytics;

const matomoConfig = analytics?.matomo;

export const matomo = matomoConfig ? createInstance({
    urlBase: matomoConfig.baseUrl,
    siteId: parseInt(matomoConfig.siteId),
    linkTracking: false,
    configurations: {
        setSecureCookie: true,
    }
}) : undefined;
