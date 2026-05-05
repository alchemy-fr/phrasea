import React, {ReactNode} from 'react';
import {
    Control,
    Controller,
    FieldPath,
    FieldValues,
    RegisterOptions,
} from 'react-hook-form';
import {FormControl} from '@mui/material';
import ColorPicker, {ColorPickerProps} from '../Color/ColorPicker';

type Props<TFieldValues extends FieldValues> = {
    control: Control<TFieldValues>;
    name: FieldPath<TFieldValues>;
    rules?: Omit<
        RegisterOptions<TFieldValues, FieldPath<TFieldValues>>,
        'valueAsNumber' | 'valueAsDate' | 'setValueAs' | 'disabled'
    >;
} & Omit<ColorPickerProps, 'color' | 'onChange'>;

export default function ColorWidget<TFieldValues extends FieldValues>({
    control,
    name,
    rules,
    ...rest
}: Props<TFieldValues>) {
    return (
        <FormControl component="fieldset">
            <Controller
                control={control}
                render={({field: {onChange, value}}) => {
                    return (
                        <ColorPicker
                            color={value || undefined}
                            onChange={onChange}
                            {...rest}
                        />
                    );
                }}
                name={name}
                rules={rules}
            />
        </FormControl>
    );
}
