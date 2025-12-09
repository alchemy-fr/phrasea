import React, {useRef} from 'react';
import AppsIcon from '@mui/icons-material/Apps';
import {Paper, Popover, PopoverProps} from '@mui/material';

type Props = {
    dashboardBaseUrl: string;
    popoverId?: string;
    children: (props: {
        open: boolean;
        icon: React.ReactNode;
        onClick: (event: React.MouseEvent<HTMLElement>) => void;
    }) => React.ReactNode;
    popoverProps?: PopoverProps;
};

export default function DashboardMenu({
    dashboardBaseUrl,
    children,
    popoverId = 'phrasea-services-popover',
    popoverProps,
}: Props) {
    const [anchorEl, setAnchorEl] = React.useState<HTMLElement | null>(null);

    const handlePopoverOpen = (event: React.MouseEvent<HTMLElement>) => {
        openedOnceRef.current = true;
        setAnchorEl(event.currentTarget);
    };

    const handlePopoverClose = () => {
        setAnchorEl(null);
    };
    const open = Boolean(anchorEl);
    const openedOnceRef = useRef<boolean>(false);

    return (
        <>
            {children({
                open,
                icon: <AppsIcon />,
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
                            '&, iframe': {
                                minWidth: {
                                    xs: '100vw',
                                    sm: 350,
                                },
                                minHeight: 350,
                            },
                        },
                    },
                }}
                {...popoverProps}
            >
                {(open || openedOnceRef.current) && (
                    <Paper
                        style={{
                            overflow: 'hidden',
                        }}
                    >
                        <iframe
                            title={'services-menu'}
                            src={`${dashboardBaseUrl}/menu.html`}
                            seamless
                            style={{
                                border: '0',
                            }}
                        />
                    </Paper>
                )}
            </Popover>
        </>
    );
}
