import {afterEach, describe, expect, it} from 'vitest';
import {
    decodePasswords,
    encodePasswords,
    getPasswordsHeader,
    storePassword,
} from './passwords';

describe('passwords', () => {
    afterEach(() => {
        document.cookie = 'passwds=; path=/; max-age=0';
    });

    it('round-trips the passwords', () => {
        expect(decodePasswords(encodePasswords({a: 'x', b: 'y'}))).toEqual({
            a: 'x',
            b: 'y',
        });
    });

    it('ignores a broken value', () => {
        expect(decodePasswords('%%%')).toEqual({});
        expect(decodePasswords(btoa('[1]'))).toEqual({});
        expect(decodePasswords(undefined)).toEqual({});
    });

    it('stores the password of each security container', () => {
        expect(getPasswordsHeader()).toBeUndefined();
        storePassword('c1', 'one');
        storePassword('c2', 'two');
        storePassword('c1', 'uno');
        expect(decodePasswords(getPasswordsHeader())).toEqual({
            c1: 'uno',
            c2: 'two',
        });
    });
});
