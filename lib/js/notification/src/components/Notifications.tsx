import {Bell, Inbox, InboxContent} from '@novu/react';
import {Popover, PopoverProps} from '@mui/material';
import React from 'react';
import {NotificationUriHandler} from '../types';

type Props = {
    appIdentifier: string;
    socketUrl: string;
    apiUrl: string;
    userId: string;
    uriHandler?: NotificationUriHandler;
    children: (props: {
        bellIcon: React.ReactNode;
        onClick: (event: React.MouseEvent<HTMLElement>) => void;
    }) => React.ReactNode;
    popoverId?: string;
    popoverProps?: PopoverProps;
};

export default function Notifications({
    appIdentifier,
    socketUrl,
    apiUrl,
    userId,
    uriHandler,
    children,
    popoverId = 'notifications-popover',
    popoverProps,
}: Props) {
    const [anchorEl, setAnchorEl] = React.useState<HTMLElement | null>(null);

    const handlePopoverOpen = (event: React.MouseEvent<HTMLElement>) => {
        setAnchorEl(event.currentTarget);
    };

    const handlePopoverClose = () => {
        setAnchorEl(null);
    };
    const open = Boolean(anchorEl);

    return (
        <>
            <Inbox
                applicationIdentifier={appIdentifier}
                subscriberId={userId}
                socketUrl={socketUrl}
                backendUrl={apiUrl}
                routerPush={uriHandler}
            >
                {children({
                    bellIcon: <Bell />,
                    onClick: handlePopoverOpen,
                })}

                <Popover
                    id={popoverId}
                    open={open}
                    anchorEl={anchorEl}
                    anchorOrigin={{
                        vertical: 'bottom',
                        horizontal: 'right',
                    }}
                    transformOrigin={{
                        vertical: 'bottom',
                        horizontal: 'left',
                    }}
                    onClose={handlePopoverClose}
                    slotProps={{
                        paper: {
                            sx: {
                                'minWidth': {
                                    xs: '100vw',
                                    sm: 500,
                                },
                                '.novu': {
                                    width: '100%',
                                },
                            },
                        },
                    }}
                    {...popoverProps}
                >
                    <InboxContent />
                </Popover>
            </Inbox>
        </>
    );
}
