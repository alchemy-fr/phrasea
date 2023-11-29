import React from 'react';
import {useMatomo} from "@jonkoops/matomo-tracker-react";
import {useLocation} from "react-router-dom";
import {RouteProxyProps} from "../types";

export default function AnalyticsRouteProxy({
    component: Component,
}: RouteProxyProps) {
    const {trackPageView, enableLinkTracking} = useMatomo();
    enableLinkTracking();

    const location = useLocation();

    React.useEffect(() => {
        trackPageView && trackPageView();
    }, [location]);

    return <Component/>
}
