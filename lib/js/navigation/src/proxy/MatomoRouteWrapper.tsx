import React, {JSX} from 'react';
import {useMatomo} from "@jonkoops/matomo-tracker-react";
import {useLocation} from "react-router-dom";
import {RouteWrapperProps} from "../types";

export default function MatomoRouteWrapper({
    children,
}: RouteWrapperProps) {
    const {trackPageView, enableLinkTracking} = useMatomo();
    enableLinkTracking();

    const location = useLocation();

    React.useEffect(() => {
        trackPageView && trackPageView();
    }, [location]);

    return children as JSX.Element;
}
