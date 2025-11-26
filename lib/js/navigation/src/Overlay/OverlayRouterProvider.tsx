import React from 'react';
import {matchRoutes, renderMatches, RouteObject} from 'react-router-dom';
import {
    getOverlayRouterContext,
    TOverlayRouterContext,
} from './OverlayRouterContext';
import {Routes} from '../types';
import {createRouterProviderRoutes, RouterProviderOptions} from '../Router';

type Props = {
    queryParam: string;
    path: string;
    routes: Routes;
    options?: RouterProviderOptions;
};

export default React.memo(function OverlayRouterProvider({
    queryParam,
    routes: routeDefinitions,
    path,
    options,
}: Props) {
    const routes: RouteObject[] = React.useMemo(
        () => createRouterProviderRoutes(routeDefinitions, options),
        []
    );

    const extraParams: Record<string, string | undefined> = {};
    if (path.indexOf('?') > 0) {
        const [pathWithoutQuery, queryString] = path.split('?');
        const searchParams = new URLSearchParams(queryString);
        for (const [key, value] of searchParams.entries()) {
            extraParams[key] = value;
        }
        path = pathWithoutQuery;
    }

    const matches = matchRoutes(routes, {
        pathname: path,
    });

    const contextValue = React.useMemo<TOverlayRouterContext>(() => {
        return {
            path,
            params: matches ? matches[matches!.length - 1].params : {},
        };
    }, [path]);

    const OverlayRouteContext = getOverlayRouterContext(queryParam);

    return (
        <OverlayRouteContext.Provider value={contextValue}>
            {matches !== null
                ? renderMatches(
                      matches.map(match => ({
                          ...match,
                          params: {
                              ...match.params,
                              ...extraParams,
                          },
                      }))
                  )
                : null}
        </OverlayRouteContext.Provider>
    );
});
