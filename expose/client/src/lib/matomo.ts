import {createInstance} from '@jonkoops/matomo-tracker-react'
import config from "./config";

const instance = createInstance({
    urlBase: config.getMatomoUrl(),
    siteId: config.getMatomoSiteId(),
});
