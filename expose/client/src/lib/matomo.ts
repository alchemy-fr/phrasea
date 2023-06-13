import {createInstance} from '@jonkoops/matomo-tracker-react'
import config from "./config";

const analytics = config.getAnalytics();

const matomoConfig = analytics.matomo;

export const matomo = matomoConfig ? createInstance({
    urlBase: matomoConfig.baseUrl,
    siteId: parseInt(matomoConfig.siteId),
    linkTracking: false,
}) : undefined;
