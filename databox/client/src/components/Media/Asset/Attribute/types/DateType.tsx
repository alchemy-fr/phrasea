import {AttributeFormatterProps} from './types';
import moment from 'moment/moment';
import {TextFieldProps} from '@mui/material';
import DateTimeType, {DateFormats} from './DateTimeType.tsx';

export default class DateType extends DateTimeType {
    denormalize(value: string | undefined): string | undefined {
        return super.denormalize(value)?.replace(/T\d{2}:\d{2}(:\d{2})?/, '');
    }

    public getFieldProps(): TextFieldProps {
        return {
            type: 'date',
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
                return m.format('ll');
            default:
            case DateFormats.Medium:
                return m.format('L');
            case DateFormats.Relative:
                return m.fromNow();
            case DateFormats.Long:
                return m.format('LLLL');
            case DateFormats.Iso:
                return m.format();
        }
    }
}
