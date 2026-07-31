import React from 'react';
import {Badge, Popover, PopoverProps} from '@mui/material';
import NotificationsIcon from '@mui/icons-material/Notifications';
import type {HttpClient} from '@alchemy/api';
import {NotificationUriHandler, RegisterNotificationRealtime} from '../types';
import {useNotifications} from '../useNotifications';
import NotificationList from './NotificationList';

type Props = {
    /**
     * Authenticated HTTP client pointing at the backend that serves the
     * notifier API (`/notifications`, `/notifications/unread-count`, ...).
     */
    apiClient: HttpClient;
    userId: string;
    uriHandler?: NotificationUriHandler;
    /**
     * Subscribes to the real-time channel to receive live notifications.
     * When omitted, the component still works but only refreshes on open.
     */
    registerRealtime?: RegisterNotificationRealtime;
    realtimeChannelPrefix?: string;
    realtimeEvent?: string;
    locale?: string;
    children: (props: {
        open: boolean;
        unreadCount: number;
        bellIcon: React.ReactNode;
        onClick: (event: React.MouseEvent<HTMLElement>) => void;
    }) => React.ReactNode;
    popoverId?: string;
    popoverProps?: Partial<PopoverProps>;
};

export default function Notifications({
    apiClient,
    userId,
    uriHandler,
    registerRealtime,
    realtimeChannelPrefix,
    realtimeEvent,
    locale,
    children,
    popoverId = 'notifications-popover',
    popoverProps,
}: Props) {
    const [anchorEl, setAnchorEl] = React.useState<HTMLElement | null>(null);
    const open = Boolean(anchorEl);

    const notifications = useNotifications({
        apiClient,
        userId,
        registerRealtime,
        realtimeChannelPrefix,
        realtimeEvent,
        active: open,
    });

    const handleOpen = (event: React.MouseEvent<HTMLElement>) => {
        setAnchorEl(event.currentTarget);
    };

    const handleClose = () => {
        setAnchorEl(null);
    };

    const bellIcon = (
        <Badge badgeContent={notifications.unreadCount} color="error" max={99}>
            <NotificationsIcon />
        </Badge>
    );

    return (
        <>
            {children({
                open,
                unreadCount: notifications.unreadCount,
                bellIcon,
                onClick: handleOpen,
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
                    vertical: 'top',
                    horizontal: 'right',
                }}
                onClose={handleClose}
                slotProps={{
                    paper: {
                        sx: {
                            minWidth: {
                                xs: '100vw',
                                sm: 420,
                            },
                            maxWidth: '100vw',
                        },
                    },
                }}
                {...popoverProps}
            >
                <NotificationList
                    state={notifications}
                    uriHandler={uriHandler}
                    locale={locale}
                />
            </Popover>
        </>
    );
}
