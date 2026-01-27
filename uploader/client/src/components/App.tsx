import {
    getPath,
    MatomoRouteWrapper,
    RouterProvider,
    RouteWrapperProps,
    useNavigate,
} from '@alchemy/navigation';
import {routes} from '../routes.ts';
import RouteProxy from './RouteProxy.tsx';
import React from 'react';
import {VerticalMenuLayout} from '@alchemy/phrasea-framework';
import {useTranslation} from 'react-i18next';
import {config, keycloakClient} from '../init.ts';
import {defaultLocales as appLocales, rootDefaultLocale} from '@alchemy/i18n';
import Menu from './Menu.tsx';

type Props = {};

export default function App({}: Props) {
    return (
        <>
            <RouterProvider
                routes={routes}
                options={{
                    RouteProxyComponent: RouteProxy,
                    WrapperComponent: WrapperComponent,
                }}
            />
        </>
    );
}

function WrapperComponent({children}: RouteWrapperProps) {
    const navigate = useNavigate();
    const {t} = useTranslation();

    return (
        <>
            <MatomoRouteWrapper>
                <VerticalMenuLayout
                    config={config}
                    logoProps={{
                        onLogoClick: () => navigate(getPath(routes.index)),
                        appTitle: t('common.uploader', `Uploader`),
                    }}
                    commonMenuProps={{
                        keycloakClient,
                        appLocales,
                        defaultLocale: rootDefaultLocale,
                    }}
                    menuChildren={<Menu />}
                >
                    <div
                        style={{
                            flexGrow: 1,
                        }}
                    >
                        {children}
                    </div>
                </VerticalMenuLayout>
            </MatomoRouteWrapper>
        </>
    );
}
