import React, {JSX, ReactNode} from 'react';
import {MenuProps} from '@mui/material/Menu';
import {ButtonBaseProps} from '@mui/material';

export type CloseWrapper = (
    handler?: React.MouseEventHandler<HTMLElement>
) => React.MouseEventHandler<HTMLElement>;

export type MainButtonProps = {
    open: boolean;
    className?: string;
} & Pick<ButtonBaseProps, 'onClick' | 'aria-haspopup' | 'aria-expanded'>;

export type DropdownActionsProps = {
    mainButton: (props: MainButtonProps) => JSX.Element;
    children: (closeWrapper: CloseWrapper) => ReactNode[];
    onClose?: () => void;
} & Omit<MenuProps, 'open' | 'onClose' | 'children'>;
