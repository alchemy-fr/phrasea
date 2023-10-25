import React, {PropsWithChildren} from 'react';
import {useMatomo} from "@jonkoops/matomo-tracker-react";
import {useLocation} from 'react-router-dom';

type Props = PropsWithChildren<{}>;

export default function AnalyticsRouterProvider({children}: Props) {
    const {trackPageView, enableLinkTracking} = useMatomo();
    enableLinkTracking();

    const location = useLocation();

    React.useEffect(() => {
        trackPageView && trackPageView();
    }, [location]);

    return children as JSX.Element;
}
