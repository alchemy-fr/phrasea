import {ReactNode} from 'react';
import {
    Control,
    Controller,
    FieldPath,
    FieldValues,
    RegisterOptions,
} from 'react-hook-form';
import {
    FormControl,
    IconButton,
    InputAdornment,
    InputBase,
    InputLabel,
    OutlinedInput,
} from '@mui/material';
import CalendarMonthIcon from '@mui/icons-material/CalendarMonth';
import ClearIcon from '@mui/icons-material/Clear';
import DatePicker, {DatePickerProps} from 'react-datepicker';
import 'react-datepicker/dist/react-datepicker.css';
import {format} from 'date-fns';

type Props<TFieldValues extends FieldValues> = {
    label?: ReactNode;
    time?: boolean;
    error?: boolean;
    control: Control<TFieldValues>;
    name: FieldPath<TFieldValues>;
    rules?: Omit<
        RegisterOptions<TFieldValues, FieldPath<TFieldValues>>,
        'valueAsNumber' | 'valueAsDate' | 'setValueAs' | 'disabled'
    >;
    dateFormat?: string;
    timeFormat?: string;
    required?: boolean;
} & Partial<DatePickerProps>;

export default function DateWidget<TFieldValues extends FieldValues>({
    name,
    control,
    rules,
    time,
    label,
    required,
    error,
    dateFormat,
    timeFormat,
}: Props<TFieldValues>) {
    const dateFormats = {
        fr: 'dd/MM/yyyy',
        es: 'dd/MM/yyyy',
        de: 'dd.MM.yyyy',
        it: 'dd/MM/yyyy',
        zh: 'yyyy/MM/dd',
        en: 'MM/dd/yyyy',
    };
    const timeFormats = {
        fr: 'HH:mm',
        es: 'HH:mm',
        de: 'HH:mm',
        it: 'HH:mm',
        zh: 'HH:mm',
        en: 'hh:mm aa',
    };

    const language = navigator.language.substring(0, 2);
    dateFormat ??=
        dateFormats[language as keyof typeof dateFormats] || dateFormats.en;
    timeFormat ??=
        timeFormats[language as keyof typeof timeFormats] || timeFormats.en;

    return (
        <>
            {label ? <InputLabel>{label}</InputLabel> : null}
            <Controller
                control={control}
                name={name}
                rules={rules}
                render={({field: {onChange, value, ref}}) => {
                    const date = value ? new Date(value) : null;
                    const isValidDate = date
                        ? date instanceof Date && !isNaN(date.getTime())
                        : true;

                    const dateFormattedValue = date
                        ? format(date, dateFormat)
                        : '';
                    const timeFormattedValue = date
                        ? format(date, timeFormat)
                        : '';

                    return (
                        <DatePicker
                            shouldCloseOnSelect={true}
                            showTimeSelect={time}
                            selected={value ? date : null}
                            required={required}
                            onChange={(date: Date | null) => {
                                onChange({
                                    target: {
                                        value: date?.toISOString(),
                                    },
                                });
                            }}
                            customInput={
                                <FormControl style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 1,
                                }}>
                                    <OutlinedInput
                                        error={error || !isValidDate}
                                        inputRef={ref}
                                        placeholder={dateFormat}
                                        value={dateFormattedValue}
                                        onChange={e => {
                                            onChange({
                                                target: {
                                                    value: e.target.value,
                                                },
                                            });
                                        }}
                                        slotProps={{
                                            input: {
                                                style: {width: 120},
                                            },
                                        }}
                                        autoComplete={'off'}
                                        required={required}
                                        endAdornment={
                                            <>
                                                {time ? (
                                                    <InputBase
                                                        placeholder={timeFormat}
                                                        slotProps={{
                                                            input: {
                                                                style: {
                                                                    width: 70,
                                                                },
                                                            },
                                                        }}
                                                        value={
                                                            timeFormattedValue
                                                        }
                                                        required={required}
                                                    />
                                                ) : null}
                                                {!required && value ? (
                                                    <IconButton
                                                        onClick={e => {
                                                            e.stopPropagation();
                                                            onChange({
                                                                target: {
                                                                    value: null,
                                                                },
                                                            });
                                                        }}
                                                    >
                                                        <ClearIcon />
                                                    </IconButton>
                                                ) : null}
                                                <CalendarMonthIcon
                                                    style={{
                                                        cursor: 'pointer',
                                                    }}
                                                />
                                            </>
                                        }
                                    />
                                </FormControl>
                            }
                        />
                    );
                }}
            />
        </>
    );
}
