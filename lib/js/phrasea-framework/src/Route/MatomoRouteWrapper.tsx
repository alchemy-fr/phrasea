import {useEffect} from 'react';
import {useLocation} from 'react-router-dom';
import {RouteWrapperProps} from '@alchemy/navigation';
import {useTracking} from '../hooks/useTracking';

export default function MatomoRouteWrapper({children}: RouteWrapperProps) {
    const {trackPageView, enableLinkTracking} = useTracking();
    enableLinkTracking();

    const location = useLocation();

    useEffect(() => {
        trackPageView();
    }, [location]);

    return children;
}
