import React, {ReactNode} from 'react';
import {
    Control,
    Controller,
    FieldPath,
    FieldValues,
    RegisterOptions,
} from 'react-hook-form';
import {
    FormControl,
    FormControlLabel,
    FormLabel,
    Icon,
    Radio,
    RadioGroup,
} from '@mui/material';
import {SwitchProps} from '@mui/material/Switch/Switch';
import {alpha} from '@mui/material/styles';

type Props<TFieldValues extends FieldValues> = {
    label?: ReactNode;
    control: Control<TFieldValues>;
    name: FieldPath<TFieldValues>;
    rules?: Omit<
        RegisterOptions<TFieldValues, FieldPath<TFieldValues>>,
        'valueAsNumber' | 'valueAsDate' | 'setValueAs' | 'disabled'
    >;
    options: RadioOption[];
} & SwitchProps;

type RadioOption = {
    label: ReactNode;
    value: string;
    icon?: React.ElementType;
    disabled?: boolean;
};

enum Classes {
    LabelWithIcon = 'label-with-icon',
}

export default function RadioWidget<TFieldValues extends FieldValues>({
    name,
    control,
    rules,
    label,
    options,
}: Props<TFieldValues>) {
    return (
        <FormControl component="fieldset">
            {label ? <FormLabel component="legend">{label}</FormLabel> : null}
            <Controller
                rules={rules}
                control={control}
                name={name}
                render={({field}) => (
                    <RadioGroup
                        {...field}
                        sx={theme => ({
                            'flexDirection': 'row',
                            'gap': 2,
                            '> label': {
                                'minWidth': 150,
                                'display': 'flex',
                                'gap': 0,
                                'flexDirection': 'column',
                                'border': '1px solid rgba(0, 0, 0, 0.23)',
                                'borderRadius': 3,
                                'p': 2,
                                '&:has(input:checked)': {
                                    bgcolor: alpha(
                                        theme.palette.success.main,
                                        0.2
                                    ),
                                    color: theme.palette.success.main,
                                    borderColor: theme.palette.success.main,
                                },
                            },
                            [`.${Classes.LabelWithIcon}`]: {
                                display: 'flex',
                                flexDirection: 'column',
                                alignItems: 'center',
                                gap: 1,
                            },
                        })}
                    >
                        {options.map(option => (
                            <FormControlLabel
                                key={option.value}
                                value={option.value}
                                control={<Radio />}
                                disabled={option.disabled}
                                label={
                                    <div className={Classes.LabelWithIcon}>
                                        {option.icon ? (
                                            <Icon>
                                                {React.createElement(
                                                    option.icon
                                                )}
                                            </Icon>
                                        ) : null}
                                        {option.label}
                                    </div>
                                }
                            />
                        ))}
                    </RadioGroup>
                )}
            />
        </FormControl>
    );
}
