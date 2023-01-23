import * as React from 'react';
import Button, {ButtonProps} from '@mui/material/Button';
import ButtonGroup from '@mui/material/ButtonGroup';
import ArrowDropDownIcon from '@mui/icons-material/ArrowDropDown';
import ClickAwayListener from '@mui/material/ClickAwayListener';
import Grow from '@mui/material/Grow';
import Paper from '@mui/material/Paper';
import Popper from '@mui/material/Popper';
import MenuItem from '@mui/material/MenuItem';
import MenuList from '@mui/material/MenuList';
import {Asset, File} from "../../../../types";
import SaveFileAsNewAssetDialog from "./SaveFileAsNewAssetDialog";
import {useModals} from "../../../../hooks/useModalStack";
import ReplaceAssetWithFileDialog from "./ReplaceAssetWithFileDialog";
import SaveFileAsRenditionDialog from "./SaveFileAsRenditionDialog";

type Props = {
    asset: Asset;
    file: File;
    variant?: ButtonProps['variant'];
};

export default function SaveAsButton({
                                         file,
                                         asset,
                                        variant = 'contained',
                                     }: Props) {
    const [open, setOpen] = React.useState(false);
    const anchorRef = React.useRef<HTMLDivElement>(null);
    const {openModal} = useModals();

    const options = [
        {
            title: `New asset`,
            component: SaveFileAsNewAssetDialog,
        },
        {
            title: `Rendition`,
            component: SaveFileAsRenditionDialog,
        },
        {
            title: `Replace asset source`,
            component: ReplaceAssetWithFileDialog,
        },
    ];

    const handleMenuItemClick = (
        event: React.MouseEvent<HTMLLIElement, MouseEvent>,
        index: number,
    ) => {
        const item = options[index];
        openModal(item.component, {
            asset,
            file,
        });
        setOpen(false);
    };

    const handleToggle = () => {
        setOpen((prevOpen) => !prevOpen);
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

    return <>
        <ButtonGroup
            variant={variant}
            ref={anchorRef}
        >
            <Button
                onClick={handleToggle}
                aria-controls={open ? 'split-button-menu' : undefined}
                aria-expanded={open ? 'true' : undefined}
                aria-label="save"
                aria-haspopup="menu"
                endIcon={<ArrowDropDownIcon/>}
            >
                Save
            </Button>
        </ButtonGroup>
        <Popper
            sx={{
                zIndex: 1,
            }}
            open={open}
            anchorEl={anchorRef.current}
            placement={'bottom-start'}
            role={undefined}
            transition
            disablePortal
        >
            {({TransitionProps, placement}) => (
                <Grow
                    {...TransitionProps}
                    style={{
                        transformOrigin:
                            placement === 'bottom' ? 'center top' : 'center bottom',
                    }}
                >
                    <Paper>
                        <ClickAwayListener onClickAway={handleClose}>
                            <MenuList id="split-button-menu" autoFocusItem>
                                {options.map((option, index) => (
                                    <MenuItem
                                        key={option.title}
                                        onClick={(event) => handleMenuItemClick(event, index)}
                                    >
                                        {option.title}
                                    </MenuItem>
                                ))}
                            </MenuList>
                        </ClickAwayListener>
                    </Paper>
                </Grow>
            )}
        </Popper>
    </>
}
