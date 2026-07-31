import React from 'react';
import {Box, ListItemButton, Tooltip, Typography} from '@mui/material';
import {useTranslation} from 'react-i18next';
import type {Notification, NotificationUriHandler} from '../types';
import {formatRelativeTime} from '../time';

type Props = {
    notification: Notification;
    onRead: (id: string) => void;
    uriHandler?: NotificationUriHandler;
    locale?: string;
};

export default function NotificationItem({
    notification,
    onRead,
    uriHandler,
    locale,
}: Props) {
    const {t} = useTranslation();
    const uri = notification.data?.uri;
    const clickable = Boolean(uri && uriHandler);

    const handleClick = () => {
        if (!notification.read) {
            onRead(notification.id);
        }
        if (uri && uriHandler) {
            uriHandler(uri);
        }
    };

    return (
        <ListItemButton
            onClick={handleClick}
            alignItems="flex-start"
            sx={{
                gap: 1.5,
                alignItems: 'center',
                cursor: clickable || !notification.read ? 'pointer' : 'default',
            }}
        >
            <Tooltip
                title={
                    notification.read
                        ? t('notification.item.read', 'Read')
                        : t('notification.item.unread', 'Unread')
                }
            >
                <Box
                    component="span"
                    sx={{
                        flex: '0 0 auto',
                        width: 8,
                        height: 8,
                        borderRadius: '50%',
                        mt: '6px',
                        bgcolor: notification.read
                            ? 'transparent'
                            : 'primary.main',
                    }}
                />
            </Tooltip>
            <Box sx={{minWidth: 0, flexGrow: 1}}>
                {notification.subject ? (
                    <Typography
                        variant="subtitle2"
                        sx={{
                            fontWeight: notification.read ? 400 : 600,
                        }}
                    >
                        {notification.subject}
                    </Typography>
                ) : null}
                {notification.content ? (
                    <Typography
                        variant="body2"
                        color="text.secondary"
                        // Content is rendered from a trusted server-side Twig
                        // template (in_app.html.twig).
                        dangerouslySetInnerHTML={{__html: notification.content}}
                        sx={{
                            '& p': {m: 0},
                            'wordBreak': 'break-word',
                        }}
                    />
                ) : null}
                {notification.createdAt ? (
                    <Typography
                        variant="caption"
                        color="text.disabled"
                        sx={{display: 'block', mt: 0.5}}
                    >
                        {formatRelativeTime(notification.createdAt, locale)}
                    </Typography>
                ) : null}
            </Box>
        </ListItemButton>
    );
}
