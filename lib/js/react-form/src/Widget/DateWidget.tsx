import {ReactNode} from 'react';
import {
    Control,
    Controller,
    FieldPath,
    FieldValues,
    RegisterOptions,
} from 'react-hook-form';
import {InputLabel} from '@mui/material';
import 'react-datepicker/dist/react-datepicker.css';
import DatePicker from './DatePicker';
import {DatePickerProps} from '../types';

type Props<TFieldValues extends FieldValues> = {
    label?: ReactNode;
    control: Control<TFieldValues>;
    name: FieldPath<TFieldValues>;
    rules?: Omit<
        RegisterOptions<TFieldValues, FieldPath<TFieldValues>>,
        'valueAsNumber' | 'valueAsDate' | 'setValueAs' | 'disabled'
    >;
} & Partial<DatePickerProps>;

export default function DateWidget<TFieldValues extends FieldValues>({
    name,
    control,
    rules,
    label,
    ...datePickerProps
}: Props<TFieldValues>) {
    return (
        <>
            {label ? <InputLabel>{label}</InputLabel> : null}
            <Controller
                control={control}
                name={name}
                rules={rules}
                render={({field: {onChange, value, ref}}) => {
                    return (
                        <DatePicker
                            {...datePickerProps}
                            inputRef={ref}
                            value={value}
                            onChange={value => {
                                onChange({
                                    target: {
                                        value: value,
                                    },
                                });
                            }}
                        />
                    );
                }}
            />
        </>
    );
}
