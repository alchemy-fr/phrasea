import React, {PropsWithChildren} from 'react';
import {HeaderBarWidgetProps} from './types.ts';
import {
    AppLogo,
    HorizontalAppMenu,
    MenuOrientation,
    NavMenu,
} from '@alchemy/phrasea-framework';
import {apiClient, config, keycloakClient} from '../../../../init.ts';
import {appLocales} from '../../../../../translations/locales.ts';
import {FlexRow} from '@alchemy/phrasea-ui';
import {routes} from '../../../../routes.ts';
import {getPath, useNavigate} from '@alchemy/navigation';
import {registerWs} from '../../../../lib/pusher.ts';
import {useNotificationUriHandler} from '../../../../hooks/useNotificationUriHandler.ts';

type Props = PropsWithChildren<HeaderBarWidgetProps>;

export default function HeaderBar({title, position, links}: Props) {
    const navigate = useNavigate();
    const notificationUriHandler = useNotificationUriHandler();

    return (
        <>
            <HorizontalAppMenu
                contentEditable={false}
                sticky={position === 'fixed'}
                config={config}
                commonMenuProps={{
                    appLocales,
                    keycloakClient,
                    apiClient,
                    registerNotificationRealtime: registerWs,
                    notificationUriHandler,
                }}
            >
                <FlexRow
                    sx={{
                        gap: 1,
                        flexGrow: 1,
                    }}
                >
                    <AppLogo
                        config={config}
                        appTitle={title ?? ''}
                        onLogoClick={() => {
                            navigate(getPath(routes.home));
                        }}
                        sx={{mr: 2}}
                    />
                    <NavMenu
                        items={
                            links?.map((link, i) => ({
                                id: `link-${i}`,
                                label: link.label,
                                href: link.url,
                                target: link.target ?? '_blank',
                            })) ?? []
                        }
                        orientation={MenuOrientation.Horizontal}
                    />
                </FlexRow>
            </HorizontalAppMenu>
        </>
    );
}
