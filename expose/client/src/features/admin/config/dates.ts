import {format, isValid} from 'date-fns';

/** An API date as the value of a `datetime-local` input, in local time */
export function toLocalInput(value: string | null | undefined): string {
    if (!value) {
        return '';
    }
    const date = new Date(value);

    return isValid(date) ? format(date, "yyyy-MM-dd'T'HH:mm") : '';
}

/** The value of a `datetime-local` input as an API date; null when empty */
export function fromLocalInput(value: string): string | null {
    if (!value) {
        return null;
    }
    const date = new Date(value);

    return isValid(date) ? date.toISOString() : null;
}
