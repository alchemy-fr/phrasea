import React, {PropsWithChildren} from 'react';
import {matomo} from "../lib/matomo";
import {MatomoProvider, useMatomo} from "@jonkoops/matomo-tracker-react";
import {useLocation} from 'react-router';

type Props = PropsWithChildren<{}>;

export default function MatomoRouterProvider({children}: Props) {
    const {trackPageView} = useMatomo();

    const location = useLocation();

    React.useEffect(() => {
        // track page view on each location change
        trackPageView()
    }, [location]);

    return children as JSX.Element;
}
