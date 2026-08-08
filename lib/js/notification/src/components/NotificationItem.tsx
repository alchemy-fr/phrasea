import React from 'react';
import {
    Box,
    IconButton,
    ListItemButton,
    ListItemIcon,
    ListItemText,
    Menu,
    MenuItem,
    Tooltip,
    Typography,
} from '@mui/material';
import MoreVertIcon from '@mui/icons-material/MoreVert';
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutline';
import {useTranslation} from 'react-i18next';
import type {Notification, NotificationUriHandler} from '../types';
import {formatRelativeTime} from '../time';

type Props = {
    notification: Notification;
    onRead: (id: string) => void;
    onUnread: (id: string) => void;
    onDelete: (id: string) => void;
    uriHandler?: NotificationUriHandler;
    locale?: string;
};

export default function NotificationItem({
    notification,
    onRead,
    onUnread,
    onDelete,
    uriHandler,
    locale,
}: Props) {
    const {t} = useTranslation();
    const uri = notification.data?.uri;
    const clickable = Boolean(uri && uriHandler);

    const stopMouseDown = (e: React.MouseEvent) => {
        e.stopPropagation();
    };

    const [menuAnchor, setMenuAnchor] = React.useState<HTMLElement | null>(
        null
    );

    const handleClick = () => {
        if (!notification.read) {
            onRead(notification.id);
        }
        if (uri && uriHandler) {
            uriHandler(uri);
        }
    };

    const dotLabel = notification.read
        ? t('notification.item.mark_unread', 'Mark as unread')
        : t('notification.item.mark_read', 'Mark as read');

    // Left status dot doubles as a toggle: filled = unread (click to read),
    // hollow-on-hover = read (click to bring back to unread).
    const handleDotClick = (e: React.MouseEvent) => {
        e.stopPropagation();
        if (notification.read) {
            onUnread(notification.id);
        } else {
            onRead(notification.id);
        }
    };

    const openMenu = (e: React.MouseEvent<HTMLElement>) => {
        e.stopPropagation();
        setMenuAnchor(e.currentTarget);
    };

    const closeMenu = () => {
        setMenuAnchor(null);
    };

    const handleDelete = (e: React.MouseEvent) => {
        e.stopPropagation();
        setMenuAnchor(null);
        onDelete(notification.id);
    };

    return (
        <>
            <ListItemButton
                onClick={handleClick}
                alignItems="flex-start"
                sx={{
                    'gap': 1.5,
                    'alignItems': 'center',
                    'cursor':
                        clickable || !notification.read ? 'pointer' : 'default',
                    '&:hover .notification-item-actions': {opacity: 1},
                    '&:hover .notification-item-dot--read': {
                        borderColor: 'action.active',
                    },
                }}
            >
                <Tooltip title={dotLabel}>
                    <Box
                        component="span"
                        role="button"
                        aria-label={dotLabel}
                        onClick={handleDotClick}
                        onMouseDown={stopMouseDown}
                        className={
                            notification.read
                                ? 'notification-item-dot--read'
                                : undefined
                        }
                        sx={{
                            'flex': '0 0 auto',
                            'width': 12,
                            'height': 12,
                            'borderRadius': '50%',
                            'boxSizing': 'border-box',
                            'cursor': 'pointer',
                            'border': '2px solid transparent',
                            'bgcolor': notification.read
                                ? 'transparent'
                                : 'primary.main',
                            'transition':
                                'background-color 0.15s, border-color 0.15s',
                            '&:hover': {
                                borderColor: 'action.active',
                            },
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
                            // Content is rendered from a trusted server-side
                            // Twig template (in_app.html.twig).
                            dangerouslySetInnerHTML={{
                                __html: notification.content,
                            }}
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
                <Tooltip title={t('notification.item.actions', 'Actions')}>
                    <IconButton
                        className="notification-item-actions"
                        size="small"
                        edge="end"
                        onClick={openMenu}
                        onMouseDown={stopMouseDown}
                        aria-label={t('notification.item.actions', 'Actions')}
                        sx={{
                            flex: '0 0 auto',
                            opacity: {xs: 1, md: menuAnchor ? 1 : 0},
                            transition: 'opacity 0.15s',
                        }}
                    >
                        <MoreVertIcon fontSize="small" />
                    </IconButton>
                </Tooltip>
            </ListItemButton>
            <Menu
                anchorEl={menuAnchor}
                open={Boolean(menuAnchor)}
                onClose={closeMenu}
                anchorOrigin={{vertical: 'bottom', horizontal: 'right'}}
                transformOrigin={{vertical: 'top', horizontal: 'right'}}
            >
                <MenuItem onClick={handleDelete} onMouseDown={stopMouseDown}>
                    <ListItemIcon>
                        <DeleteOutlineIcon fontSize="small" />
                    </ListItemIcon>
                    <ListItemText>
                        {t('notification.item.delete', 'Delete')}
                    </ListItemText>
                </MenuItem>
            </Menu>
        </>
    );
}
