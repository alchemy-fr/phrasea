import {AttributeFormatterProps} from './types';
import moment from 'moment/moment';
import {TextFieldProps} from '@mui/material';
import DateType, {DateFormats} from './DateType';

export default class DateTimeType extends DateType {
    public getFieldProps(): TextFieldProps {
        return {
            type: 'datetime-local',
            InputProps: {
                inputProps: {
                    step: 1,
                },
            },
            InputLabelProps: {
                shrink: true,
            },
        };
    }

    protected format({value, format}: AttributeFormatterProps): string {
        if (!value) {
            return '';
        }

        const m = moment(typeof value === 'number' ? value * 1000 : value);

        switch (format ?? this.getAvailableFormats()[0].name) {
            case DateFormats.Short:
                return m.format('L LT');
            default:
            case DateFormats.Medium:
                return m.format('lll');
            case DateFormats.Relative:
                return m.fromNow();
            case DateFormats.Long:
                return m.format('LLLL');
            case DateFormats.Iso:
                return m.format();
        }
    }
}
