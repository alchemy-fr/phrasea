import React, {PropsWithChildren} from 'react';
import {HeaderBarWidgetProps} from './types.ts';
import ElevationScroll from './ElevationScroll.tsx';
import {
    AppLogo,
    HorizontalAppMenu,
    MenuOrientation,
    NavItem,
    NavMenu,
} from '@alchemy/phrasea-framework';
import {config, keycloakClient} from '../../../../init.ts';
import {appLocales} from '../../../../../translations/locales.ts';
import {FlexRow} from '@alchemy/phrasea-ui';
import {routes} from '../../../../routes.ts';
import {getPath, useNavigate} from '@alchemy/navigation';

type Props = PropsWithChildren<HeaderBarWidgetProps>;

export default function HeaderBar({title, position, link1, link2}: Props) {
    const navigate = useNavigate();

    const items: NavItem[] = [];
    if (link1) {
        items.push({
            id: 'link1',
            label: link1,
            href: '#',
            target: '_blank',
        });
    }
    if (link2) {
        items.push({
            id: 'link2',
            label: link2,
            href: '#',
            target: '_blank',
        });
    }

    return (
        <>
            <ElevationScroll>
                {({elevated}) => (
                    <>
                        <HorizontalAppMenu
                            sx={theme => ({
                                position:
                                    position === 'fixed'
                                        ? 'sticky'
                                        : 'relative',
                                top: 0,
                                left: 0,
                                right: 0,
                                zIndex: theme.zIndex.appBar,
                                boxShadow: elevated
                                    ? theme.shadows[4]
                                    : undefined,
                            })}
                            config={config}
                            commonMenuProps={{
                                appLocales,
                                keycloakClient,
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
                                    items={items}
                                    orientation={MenuOrientation.Horizontal}
                                />
                            </FlexRow>
                        </HorizontalAppMenu>
                    </>
                )}
            </ElevationScroll>
        </>
    );
}
