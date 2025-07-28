import * as React from 'react';
import Button, {ButtonProps} from '@mui/material/Button';
import ArrowDropDownIcon from '@mui/icons-material/ArrowDropDown';
import ClickAwayListener from '@mui/material/ClickAwayListener';
import Grow from '@mui/material/Grow';
import Paper from '@mui/material/Paper';
import Popper from '@mui/material/Popper';
import MenuItem from '@mui/material/MenuItem';
import MenuList from '@mui/material/MenuList';
import SaveFileAsNewAssetDialog, {
    BaseSaveAsProps,
} from './SaveFileAsNewAssetDialog';
import {useModals} from '@alchemy/navigation';
import ReplaceAssetWithFileDialog from './ReplaceAssetWithFileDialog';
import SaveFileAsRenditionDialog from './SaveFileAsRenditionDialog';
import {stopPropagation} from '../../../../lib/stdFuncs';
import {FC, MouseEventHandler, PropsWithChildren, ReactNode} from 'react';
import {useTranslation} from 'react-i18next';
import {ListItemIcon} from '@mui/material';

type CloseWrapper = (
    handler?: React.MouseEventHandler<HTMLElement>
) => React.MouseEventHandler<HTMLElement>;

type Props = PropsWithChildren<{
    variant?: ButtonProps['variant'];
    Component?: FC<any>;
    componentProps?: ButtonProps;
    closeWrapper?: CloseWrapper;
    icon?: ReactNode;
}> &
    BaseSaveAsProps;

export default function SaveAsButton({
    file,
    asset,
    icon,
    children,
    Component = Button,
    componentProps = {},
    closeWrapper,
    ...saveAsProps
}: Props) {
    const {t} = useTranslation();
    const [open, setOpen] = React.useState(false);
    const anchorRef = React.useRef<HTMLDivElement>(null);
    const {openModal} = useModals();

    if (!closeWrapper) {
        closeWrapper = handler => e => {
            handler?.(e);

            return () => {};
        };
    }

    const options = [
        {
            id: 'new-asset',
            title: t('save_as_button.new_asset', `New asset`),
            component: SaveFileAsNewAssetDialog,
        },
        {
            id: 'rendition',
            title: t('save_as_button.rendition', `Rendition`),
            component: SaveFileAsRenditionDialog,
        },
    ];

    if (asset.source?.id !== file.id) {
        options.push({
            id: 'replace-asset-source',
            title: t(
                'save_as_button.replace_asset_source',
                `Replace asset source`
            ),
            component: ReplaceAssetWithFileDialog,
        });
    }

    const handleMenuItemClick = (
        _event: React.MouseEvent<HTMLElement, MouseEvent>,
        index: number
    ) => {
        const item = options[index];
        openModal(item.component, {
            ...saveAsProps,
            asset,
            file,
        });
        setOpen(false);
    };

    const handleToggle = (
        e: React.MouseEvent<HTMLButtonElement, MouseEvent>
    ) => {
        e.stopPropagation();
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

    let arrowIcon: ReactNode | undefined;
    let startIcon: ReactNode | undefined;
    if (Component === Button) {
        if (icon) {
            componentProps.startIcon ??= icon;
        }
        componentProps.endIcon ??= <ArrowDropDownIcon />;
    } else if (Component === MenuItem) {
        if (icon) {
            startIcon = <ListItemIcon>{icon}</ListItemIcon>;
        }
        arrowIcon = (
            <ListItemIcon>
                <ArrowDropDownIcon />
            </ListItemIcon>
        );
    }

    return (
        <>
            <Component
                onClick={handleToggle}
                onMouseDown={stopPropagation}
                aria-controls={open ? 'split-button-menu' : undefined}
                aria-expanded={open ? 'true' : undefined}
                aria-label="save"
                aria-haspopup="menu"
                ref={anchorRef}
                {...componentProps}
            >
                {startIcon}
                {children ?? t('save_as_button.save_as', `Save as`)}
                {arrowIcon}
            </Component>
            <Popper
                sx={theme => ({
                    zIndex: theme.zIndex.tooltip,
                })}
                open={open}
                anchorEl={anchorRef.current}
                placement={'bottom-start'}
                role={undefined}
                transition
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
                                    {options.map((option, index) => (
                                        <MenuItem
                                            key={option.id}
                                            onClick={closeWrapper(event =>
                                                handleMenuItemClick(
                                                    event,
                                                    index
                                                )
                                            )}
                                            onMouseDown={stopPropagation}
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
    );
}
