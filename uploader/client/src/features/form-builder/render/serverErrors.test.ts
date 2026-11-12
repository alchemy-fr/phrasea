import {describe, expect, it} from 'vitest';
import {fromServerErrors} from './serverErrors';

describe('fromServerErrors', () => {
    it('keys the errors of the whole form under an empty key', () => {
        expect(
            fromServerErrors({
                _form: ['Unknown field "foo".'],
                album: ['This value is required.', 'Too long.'],
            })
        ).toEqual({
            '': ['Unknown field "foo".'],
            'album': ['This value is required.', 'Too long.'],
        });
    });

    it('answers no error for none', () => {
        expect(fromServerErrors({})).toEqual({});
    });
});
