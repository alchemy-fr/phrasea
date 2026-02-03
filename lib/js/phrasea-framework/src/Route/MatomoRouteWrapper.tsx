import React, {JSX} from 'react';
import {useLocation} from 'react-router-dom';
import {RouteWrapperProps} from '@alchemy/navigation';
import {useTracking} from '../hooks/useTracking';

export default function MatomoRouteWrapper({children}: RouteWrapperProps) {
    const {trackPageView, enableLinkTracking} = useTracking();
    enableLinkTracking();

    const location = useLocation();

    React.useEffect(() => {
        trackPageView();
    }, [location]);

    return children as JSX.Element;
}
