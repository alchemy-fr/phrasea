import React from 'react';
import {HexColorPicker} from 'react-colorful';
import {Popover, Stack, TextField, TextFieldProps} from '@mui/material';
import {ColorBox} from './ColorBox';

type Props = {
    color: string | undefined;
    onChange: (color: string) => void;
    disabled?: boolean;
    readOnly?: boolean;
    displayField?: boolean;
    label?: TextFieldProps['label'];
};

export default function ColorPicker({
    color,
    label,
    onChange,
    disabled,
    readOnly,
    displayField = true,
}: Props) {
    const [anchorEl, setAnchorEl] = React.useState<HTMLDivElement | null>(null);
    const open = Boolean(anchorEl) && !disabled && !readOnly;

    const inputRef = React.useRef<HTMLInputElement>();

    const handleClose = React.useCallback(() => {
        setAnchorEl(null);
    }, []);

    const toggleOpen = React.useCallback<
        React.MouseEventHandler<HTMLDivElement>
    >(e => {
        e.stopPropagation();
        setAnchorEl(p => (!p ? e.currentTarget : null));
        setTimeout(() => {
            inputRef.current?.focus();
        }, 0);
    }, []);
    const onTextChange = React.useCallback<
        React.ChangeEventHandler<HTMLInputElement>
    >(
        e => {
            onChange(e.target.value);
        },
        [onChange]
    );

    const popUpClickHandler = React.useCallback<
        React.MouseEventHandler<HTMLDivElement>
    >(e => {
        e.stopPropagation();
        inputRef.current?.focus();
    }, []);

    const height = 55;
    const borderWidth = 2;
    const isEditable = !readOnly && !disabled;

    return (
        <Stack
            direction={'row'}
            style={{
                position: 'relative',
                cursor: isEditable ? 'pointer' : undefined,
            }}
        >
            {displayField && (
                <TextField
                    label={label}
                    value={color ?? ''}
                    inputRef={inputRef}
                    onChange={onTextChange}
                    InputProps={{
                        readOnly,
                    }}
                    disabled={disabled}
                />
            )}
            <ColorBox
                color={color ?? ''}
                onMouseDown={isEditable ? toggleOpen : undefined}
                height={height}
                width={height}
                borderWidth={borderWidth}
            >
                <Popover
                    open={open}
                    anchorEl={anchorEl}
                    onClose={handleClose}
                    onMouseDown={popUpClickHandler}
                    anchorOrigin={{
                        vertical: 'bottom',
                        horizontal: 'right',
                    }}
                >
                    <HexColorPicker color={color ?? ''} onChange={onChange} />
                </Popover>
            </ColorBox>
        </Stack>
    );
}
