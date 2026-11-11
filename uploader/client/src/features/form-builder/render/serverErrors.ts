import type {FormErrors} from '@/types/api';
import type {FormFieldErrors} from '../types';

/**
 * The errors answered by the API, keyed like the client ones: those of the
 * whole form (`_form`) under `''`.
 */
export function fromServerErrors(errors: FormErrors): FormFieldErrors {
    const result: FormFieldErrors = {};
    for (const [field, messages] of Object.entries(errors)) {
        result[field === '_form' ? '' : field] = messages;
    }

    return result;
}
