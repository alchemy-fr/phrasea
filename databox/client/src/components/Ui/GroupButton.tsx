import {PropsWithChildren, ReactNode} from 'react';
import {
    Button,
    ButtonGroup,
    ClickAwayListener,
    Grow,
    ListItemIcon,
    MenuItem,
    MenuList,
    Paper,
    Popper,
} from '@mui/material';
import ArrowDropDownIcon from '@mui/icons-material/ArrowDropDown';

type Action = {
    id: string;
    label: ReactNode;
    startIcon?: ReactNode;
    onClick: () => void;
    disabled?: boolean;
};

type Props = PropsWithChildren<{
    id: string;
    actions: Action[];
    onClick: () => void;
    startIcon?: ReactNode;
    disabled?: boolean;
}>;

export default function GroupButton({
    actions,
    children,
    onClick,
    startIcon,
    disabled,
}: Props) {
    const [open, setOpen] = React.useState(false);
    const anchorRef = React.useRef<HTMLDivElement>(null);

    const handleMenuItemClick = (
        event: React.MouseEvent<HTMLLIElement, MouseEvent>,
        index: number
    ) => {
        setOpen(false);
        actions[index].onClick();
    };

    const handleToggle = () => {
        setOpen(prevOpen => !prevOpen);
    };

    const handleClose = (event: Event) => {
        if (
            anchorRef.current &&
            anchorRef.current.contains(event.target as HTMLElement)
        ) {
            return;
        }

        setOpen(false);
    };

    return (
        <>
            <ButtonGroup
                variant="contained"
                ref={anchorRef}
                aria-label="split button"
                disabled={disabled}
                disableElevation={true}
                style={{
                    verticalAlign: 'middle',
                }}
            >
                <Button
                    onClick={onClick}
                    disabled={disabled}
                    startIcon={startIcon}
                >
                    {children}
                </Button>
                <Button
                    disabled={disabled}
                    size="small"
                    sx={{
                        p: 0,
                    }}
                    aria-controls={open ? 'split-button-menu' : undefined}
                    aria-expanded={open ? 'true' : undefined}
                    aria-label="Select edit action"
                    aria-haspopup="menu"
                    onClick={handleToggle}
                >
                    <ArrowDropDownIcon />
                </Button>
            </ButtonGroup>
            <Popper
                open={open}
                anchorEl={anchorRef.current}
                role={undefined}
                transition
                disablePortal
            >
                {({TransitionProps, placement}) => (
                    <Grow
                        {...TransitionProps}
                        style={{
                            transformOrigin:
                                placement === 'bottom'
                                    ? 'center top'
                                    : 'center bottom',
                        }}
                    >
                        <Paper>
                            <ClickAwayListener onClickAway={handleClose}>
                                <MenuList id="split-button-menu" autoFocusItem>
                                    {actions.map((action, index) => (
                                        <MenuItem
                                            key={action.id}
                                            disabled={action.disabled}
                                            onClick={e =>
                                                handleMenuItemClick(e, index)
                                            }
                                        >
                                            {action.startIcon && (
                                                <ListItemIcon>
                                                    {action.startIcon}
                                                </ListItemIcon>
                                            )}
                                            {action.label}
                                        </MenuItem>
                                    ))}
                                </MenuList>
                            </ClickAwayListener>
                        </Paper>
                    </Grow>
                )}
            </Popper>
        </>
    );
}
