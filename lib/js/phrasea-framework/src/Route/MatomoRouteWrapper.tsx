import React, {JSX} from 'react';
import {useMatomo} from '@jonkoops/matomo-tracker-react';
import {useLocation} from 'react-router-dom';
import {RouteWrapperProps} from '@alchemy/navigation';

export default function MatomoRouteWrapper({children}: RouteWrapperProps) {
    const {trackPageView, enableLinkTracking, pushInstruction} = useMatomo();
    enableLinkTracking();

    const location = useLocation();

    React.useEffect(() => {
        trackPageView && trackPageView();
        pushInstruction('trackVisibleContentImpressions');
    }, [location]);

    return children as JSX.Element;
}
