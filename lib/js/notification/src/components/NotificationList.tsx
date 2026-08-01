import React from 'react';
import {
    Badge,
    Box,
    Button,
    CircularProgress,
    Divider,
    IconButton,
    List,
    Tooltip,
    Typography,
} from '@mui/material';
import DoneAllIcon from '@mui/icons-material/DoneAll';
import NotificationsOffIcon from '@mui/icons-material/NotificationsOff';
import RefreshIcon from '@mui/icons-material/Refresh';
import {useTranslation} from 'react-i18next';
import type {NotificationUriHandler} from '../types';
import type {UseNotificationsResult} from '../useNotifications';
import NotificationItem from './NotificationItem';

type Props = {
    state: UseNotificationsResult;
    uriHandler?: NotificationUriHandler;
    locale?: string;
};

export default function NotificationList({state, uriHandler, locale}: Props) {
    const {t} = useTranslation();
    const {
        items,
        loading,
        loaded,
        hasMore,
        unreadCount,
        loadMore,
        refresh,
        markRead,
        markAllRead,
    } = state;

    return (
        <Box sx={{display: 'flex', flexDirection: 'column', maxHeight: '70vh'}}>
            <Box
                sx={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    px: 2,
                    py: 1.5,
                }}
            >
                <Badge
                    badgeContent={unreadCount}
                    color="error"
                    max={99}
                    sx={{'& .MuiBadge-badge': {right: -10, top: 2}}}
                >
                    <Typography variant="h6" component="div">
                        {t('notification.list.title', 'Notifications')}
                    </Typography>
                </Badge>
                <Box sx={{display: 'flex', alignItems: 'center', gap: 0.5}}>
                    <Tooltip title={t('notification.list.refresh', 'Refresh')}>
                        <span>
                            <IconButton
                                size="small"
                                onClick={refresh}
                                disabled={loading}
                                aria-label={t(
                                    'notification.list.refresh',
                                    'Refresh'
                                )}
                            >
                                <RefreshIcon fontSize="small" />
                            </IconButton>
                        </span>
                    </Tooltip>
                    <Button
                        size="small"
                        startIcon={<DoneAllIcon />}
                        onClick={markAllRead}
                        disabled={unreadCount === 0}
                    >
                        {t(
                            'notification.list.mark_all_read',
                            'Mark all as read'
                        )}
                    </Button>
                </Box>
            </Box>
            <Divider />

            <Box sx={{overflowY: 'auto', flexGrow: 1}}>
                {items.length > 0 ? (
                    <List disablePadding>
                        {items.map(notification => (
                            <NotificationItem
                                key={notification.id}
                                notification={notification}
                                onRead={markRead}
                                uriHandler={uriHandler}
                                locale={locale}
                            />
                        ))}
                    </List>
                ) : null}

                {loaded && items.length === 0 && !loading ? (
                    <Box
                        sx={{
                            display: 'flex',
                            flexDirection: 'column',
                            alignItems: 'center',
                            gap: 1,
                            color: 'text.secondary',
                            py: 5,
                        }}
                    >
                        <NotificationsOffIcon fontSize="large" />
                        <Typography variant="body2">
                            {t(
                                'notification.list.empty',
                                'No notifications yet'
                            )}
                        </Typography>
                    </Box>
                ) : null}

                {loading ? (
                    <Box
                        sx={{
                            display: 'flex',
                            justifyContent: 'center',
                            py: 3,
                        }}
                    >
                        <CircularProgress size={24} />
                    </Box>
                ) : null}

                {hasMore && !loading ? (
                    <Box sx={{display: 'flex', justifyContent: 'center', p: 1}}>
                        <Button size="small" onClick={loadMore}>
                            {t('notification.list.load_more', 'Load more')}
                        </Button>
                    </Box>
                ) : null}
            </Box>
        </Box>
    );
}
