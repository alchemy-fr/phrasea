import {DatePickerProps} from '../types';
import DatePickerBase from 'react-datepicker';
import {FormControl, IconButton, InputBase, OutlinedInput} from '@mui/material';
import {useCallback, useMemo, useRef, useState} from 'react';
import ClearIcon from '@mui/icons-material/Clear';
import CalendarMonthIcon from '@mui/icons-material/CalendarMonth';
import {format, parse} from 'date-fns';

export default function DatePicker({
    time,
    required,
    error,
    dateFormat,
    timeFormat,
    value,
    onChange,
    inputRef,
}: DatePickerProps) {
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

    const [date, initialDateValue, initialTimeValue] = useMemo(() => {
        const d = value ? new Date(value) : null;
        if (d instanceof Date && !isNaN(d.getTime())) {
            const dateStr = format(d, dateFormat);
            const timeStr = time ? format(d, timeFormat) : '';
            return [d, dateStr, timeStr];
        }
        return [null, '', ''];
    }, [value]);

    const [dateValue, setDateValue] = useState(initialDateValue);
    const [timeValue, setTimeValue] = useState(initialTimeValue);

    const parseDate = useCallback(
        (
            newDateValue: string | null,
            newTimeValue: string | null
        ): Date | null => {
            newDateValue ??= dateValue;
            newTimeValue ??= timeValue;

            if (newDateValue) {
                const d = parse(
                    `${newDateValue} - ${newTimeValue}`,
                    `${dateFormat} - ${timeFormat}`,
                    new Date()
                );

                if (d instanceof Date && !isNaN(d.getTime())) {
                    return d;
                }
            }

            return null;
        },
        [dateValue, timeValue, dateFormat, timeFormat]
    );

    const updateDate = useCallback(
        (
            newDateValue: string | null,
            newTimeValue: string | null
        ): Date | null => {
            if (null !== newDateValue) {
                setDateValue(newDateValue);
            }
            if (null !== newTimeValue) {
                setTimeValue(newTimeValue);
            }

            return parseDate(newDateValue, newTimeValue);
        },
        [parseDate]
    );

    const currentDate = parseDate(dateValue, timeValue);

    const dateIsInvalid = (dateValue || timeValue) && !currentDate;

    return (
        <DatePickerBase
            shouldCloseOnSelect={true}
            showTimeSelect={time}
            selected={currentDate}
            required={required}
            onChange={(date: Date | null) => {
                onChange(date?.toISOString() || null);

                if (date) {
                    const dateStr = format(date, dateFormat);
                    setDateValue(dateStr);
                    if (time) {
                        const timeStr = format(date, timeFormat);
                        setTimeValue(timeStr);
                    }
                } else {
                    setDateValue('');
                    setTimeValue('');
                }
            }}
            customInput={
                <FormControl
                    style={{
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: 1,
                    }}
                >
                    <OutlinedInput
                        error={Boolean(error || dateIsInvalid)}
                        inputRef={inputRef}
                        placeholder={dateFormat}
                        value={dateValue}
                        onChange={e => {
                            const newDate = updateDate(e.target.value, null);
                            if (newDate) {
                                onChange(newDate.toISOString());
                            }
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
                                        value={timeValue}
                                        required={required}
                                        onChange={e => {
                                            const newDate = updateDate(
                                                null,
                                                e.target.value
                                            );
                                            if (newDate) {
                                                onChange(newDate.toISOString());
                                            }
                                        }}
                                    />
                                ) : null}
                                {!required && value ? (
                                    <IconButton
                                        onClick={e => {
                                            e.stopPropagation();
                                            setDateValue('');
                                            setTimeValue('');
                                            onChange(null);
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
}
