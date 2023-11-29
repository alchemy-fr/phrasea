import {ReactNode} from 'react';
import {Controller} from 'react-hook-form';
import {FieldValues} from 'react-hook-form/dist/types/fields';
import {Control} from 'react-hook-form/dist/types/form';
import {FieldPath} from 'react-hook-form/dist/types';
import {RegisterOptions} from 'react-hook-form/dist/types/validator';
import {FormControlLabel, Switch} from '@mui/material';
import {SwitchProps} from '@mui/material/Switch/Switch';

type Props<TFieldValues extends FieldValues> = {
    label?: ReactNode;
    control: Control<TFieldValues>;
    name: FieldPath<TFieldValues>;
    rules?: Omit<
        RegisterOptions<TFieldValues, FieldPath<TFieldValues>>,
        'valueAsNumber' | 'valueAsDate' | 'setValueAs' | 'disabled'
    >;
} & SwitchProps;

export default function SwitchWidget<TFieldValues extends FieldValues>({
    name,
    control,
    rules,
    label,
    ...switchProps
}: Props<TFieldValues>) {
    return (
        <Controller
            control={control}
            name={name}
            rules={rules}
            render={({field: {onChange, value, ref}}) => {
                return (
                    <FormControlLabel
                        control={
                            <Switch
                                {...switchProps}
                                ref={ref}
                                checked={value}
                                onChange={onChange}
                            />
                        }
                        label={label}
                    />
                );
            }}
        />
    );
}
