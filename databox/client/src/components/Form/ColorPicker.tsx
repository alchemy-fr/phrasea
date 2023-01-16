import React, {PropsWithChildren} from 'react';
import {HexColorPicker} from "react-colorful";
import {Stack, TextField, TextFieldProps} from "@mui/material";

type ColorBoxProps = PropsWithChildren<{
    color: string;
    width?: number;
    height?: number;
    borderWidth?: number;
}> & React.HTMLProps<HTMLDivElement>;

export function ColorBox({
                             color,
                             width = 30,
                             height = 22,
                             borderWidth = 2,
                             children,
                             style,
                             ...divProps
                         }: ColorBoxProps) {
    return <div
        style={{
            width,
            height,
            backgroundColor: color,
            border: `${borderWidth}px solid #000`,
            ...(style || {})
        }}
        {...divProps}
    >{children}</div>
}

type Props = {
    color: string | undefined;
    onChange: (color: string) => void;
    disabled?: boolean;
    readOnly?: boolean;
    label?: TextFieldProps['label'];
};

export default function ColorPicker({
                                        color,
                                        label,
                                        onChange,
                                        disabled,
                                        readOnly,
                                    }: Props) {
    const [open, setOpen] = React.useState(false);
    const inputRef = React.useRef<HTMLInputElement>();

    const toggleOpen = React.useCallback<React.MouseEventHandler<HTMLDivElement>>((e) => {
        e.stopPropagation();
        setOpen(p => {
            if (!p) {
                setTimeout(() => {
                    if (inputRef.current) {
                        inputRef.current!.focus();
                    }
                }, 0);
            }

            return !p;
        });

    }, []);
    const doOpen = React.useCallback<React.FocusEventHandler<HTMLInputElement>>((e) => {
        setOpen(true);
    }, []);
    const doClose = React.useCallback<React.FocusEventHandler<HTMLInputElement>>((e) => {
        setOpen(false);
    }, []);
    const onTextChange = React.useCallback<React.ChangeEventHandler<HTMLInputElement>>((e) => {
        onChange(e.target.value);
    }, [onChange]);

    const popUpClickHandler = React.useCallback<React.MouseEventHandler<HTMLDivElement>>((e) => {
        e.stopPropagation();
        inputRef.current!.focus();
    }, []);

    const height = 55;
    const borderWidth = 2;
    const isEditable = !readOnly && !disabled;

    return <Stack
        direction={'row'}
        style={{
            position: 'relative',
            cursor: isEditable ? 'pointer' : undefined,
        }}
    >
        <TextField
            label={label}
            value={color ?? ''}
            inputRef={inputRef}
            onChange={onTextChange}
            onFocus={isEditable ? doOpen : undefined}
            onBlur={doClose}
            InputProps={{
                readOnly,
            }}
            disabled={disabled}
        />
        <ColorBox
            color={color ?? ''}
            onMouseDown={isEditable ? toggleOpen : undefined}
            height={height}
            width={height}
            borderWidth={borderWidth}
        >
            {open && !disabled && !readOnly && <div
                onMouseDown={popUpClickHandler}
                onClick={popUpClickHandler}
                style={{
                    position: 'absolute',
                    top: height + borderWidth,
                    left: 0,
                    zIndex: '10',
                }}
            >
                <HexColorPicker
                    color={color ?? ''}
                    onChange={onChange}
                />
            </div>}
        </ColorBox>
    </Stack>
}
