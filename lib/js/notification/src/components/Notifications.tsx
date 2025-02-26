import {Bell, Inbox, InboxContent} from '@novu/react';
import {IconButton, Popover} from '@mui/material';
import React from 'react';
import {NotificationUriHandler} from '../types';

type Props = {
    appIdentifier: string;
    socketUrl: string;
    apiUrl: string;
    userId: string;
    uriHandler?: NotificationUriHandler;
};

export default function Notifications({
    appIdentifier,
    socketUrl,
    apiUrl,
    userId,
    uriHandler,
}: Props) {
    const [anchorEl, setAnchorEl] = React.useState<HTMLElement | null>(null);

    const handlePopoverOpen = (event: React.MouseEvent<HTMLElement>) => {
        setAnchorEl(event.currentTarget);
    };

    const handlePopoverClose = () => {
        setAnchorEl(null);
    };
    const open = Boolean(anchorEl);
    const popoverId = 'notifications-popover';

    return (
        <>
            <Inbox
                applicationIdentifier={appIdentifier}
                subscriberId={userId}
                socketUrl={socketUrl}
                backendUrl={apiUrl}
                routerPush={uriHandler}
            >
                <IconButton
                    aria-owns={open ? popoverId : undefined}
                    aria-haspopup="true"
                    onClick={handlePopoverOpen}
                    sx={{
                        '.nt-text-foreground': {
                            color: 'primary.contrastText',
                        },
                    }}
                >
                    <Bell />
                </IconButton>

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
                >
                    <InboxContent />
                </Popover>
            </Inbox>
        </>
    );
}
