import React, {PropsWithChildren, ReactNode} from 'react';
import {
    Button,
    ButtonGroup,
    ButtonGroupProps,
    ClickAwayListener,
    Grow,
    ListItemIcon,
    MenuItem,
    MenuList,
    Paper,
    Popper,
} from '@mui/material';
import ArrowDropDownIcon from '@mui/icons-material/ArrowDropDown';
import {ButtonProps} from '@mui/material/Button';

export type GroupButtonAction = {
    id: string;
    label: ReactNode;
    startIcon?: ReactNode;
    onClick: () => void;
    disabled?: boolean;
};

type Props = PropsWithChildren<{
    id: string;
    buttonGroupProps?: Partial<ButtonGroupProps>;
    actions: GroupButtonAction[];
}> &
    ButtonProps;

export default function GroupButton({
    actions,
    disabled,
    buttonGroupProps = {},
    ...buttonProps
}: Props) {
    const [open, setOpen] = React.useState(false);
    const anchorRef = React.useRef<HTMLDivElement>(null);

    const handleMenuItemClick = (
        _event: React.MouseEvent<HTMLLIElement, MouseEvent>,
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
                color={buttonProps.color}
                variant="contained"
                ref={anchorRef}
                aria-label="split button"
                disabled={disabled}
                disableElevation={true}
                style={{
                    verticalAlign: 'middle',
                }}
                {...buttonGroupProps}
            >
                <Button {...buttonProps} />
                {actions.length > 0 && (
                    <Button
                        disabled={disabled}
                        size="small"
                        sx={{
                            p: 0,
                        }}
                        color={buttonProps.color}
                        aria-controls={open ? 'split-button-menu' : undefined}
                        aria-expanded={open ? 'true' : undefined}
                        aria-label="Select edit action"
                        aria-haspopup="menu"
                        onClick={handleToggle}
                    >
                        <ArrowDropDownIcon />
                    </Button>
                )}
            </ButtonGroup>
            {actions.length > 0 && (
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
                                    <MenuList
                                        id="split-button-menu"
                                        autoFocusItem
                                    >
                                        {actions.map((action, index) => (
                                            <MenuItem
                                                key={action.id}
                                                disabled={action.disabled}
                                                onClick={e =>
                                                    handleMenuItemClick(
                                                        e,
                                                        index
                                                    )
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
            )}
        </>
    );
}
