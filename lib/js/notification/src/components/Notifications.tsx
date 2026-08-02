import React from 'react';
import {Badge, Popover, PopoverProps} from '@mui/material';
import NotificationsIcon from '@mui/icons-material/Notifications';
import type {HttpClient} from '@alchemy/api';
import {
    NotificationChannel,
    NotificationUriHandler,
    RegisterNotificationRealtime,
} from '../types';
import {useNotifications} from '../useNotifications';
import NotificationList from './NotificationList';
import NotificationPreferences from './NotificationPreferences';

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
    /**
     * When `true` (default), a settings icon in the list header lets the user
     * open the notification preferences panel inside the popover. Set to
     * `false` to hide it (e.g. when preferences live on a dedicated page).
     */
    preferences?: boolean;
    /** Optional display-name overrides for the preferences panel. */
    topicLabel?: (topic: string) => string;
    channelLabel?: (channel: NotificationChannel) => string;
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
    preferences = true,
    topicLabel,
    channelLabel,
    children,
    popoverId = 'notifications-popover',
    popoverProps,
}: Props) {
    const [anchorEl, setAnchorEl] = React.useState<HTMLElement | null>(null);
    const open = Boolean(anchorEl);
    const [showPreferences, setShowPreferences] = React.useState(false);

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
        // Reset to the list view for the next time the popover opens.
        setShowPreferences(false);
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
                {preferences && showPreferences ? (
                    <NotificationPreferences
                        apiClient={apiClient}
                        active={open}
                        topicLabel={topicLabel}
                        channelLabel={channelLabel}
                        onBack={() => setShowPreferences(false)}
                    />
                ) : (
                    <NotificationList
                        state={notifications}
                        uriHandler={uriHandler}
                        locale={locale}
                        onOpenPreferences={
                            preferences
                                ? () => setShowPreferences(true)
                                : undefined
                        }
                    />
                )}
            </Popover>
        </>
    );
}
