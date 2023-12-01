import {ReactNode} from 'react';
import {Controller} from 'react-hook-form';
import {Checkbox, FormControlLabel} from '@mui/material';
import {FieldValues} from 'react-hook-form';
import {Control} from 'react-hook-form';
import {FieldPath} from 'react-hook-form';
import {RegisterOptions} from 'react-hook-form';

type Props<
    TFieldValues extends FieldValues,
    TName extends FieldPath<TFieldValues> = FieldPath<TFieldValues>
> = {
    label?: ReactNode;
    control: Control<TFieldValues>;
    name: TName;
    disabled?: boolean | undefined;
    rules?: Omit<
        RegisterOptions<TFieldValues, TName>,
        'valueAsNumber' | 'valueAsDate' | 'setValueAs' | 'disabled'
    >;
};

export default function CheckboxWidget<TFieldValues extends FieldValues>({
    name,
    label,
    control,
    disabled,
    rules,
}: Props<TFieldValues>) {
    return (
        <FormControlLabel
            control={
                <Controller
                    name={name}
                    control={control}
                    rules={rules}
                    render={({field}) => (
                        <Checkbox
                            {...field}
                            disabled={disabled}
                            checked={field.value}
                            onChange={e => field.onChange(e.target.checked)}
                        />
                    )}
                />
            }
            disabled={disabled}
            label={label}
            labelPlacement="end"
        />
    );
}
