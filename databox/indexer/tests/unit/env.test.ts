import {getEnv} from '../../src/env';

describe('getEnv', () => {
    const key = 'A_TEST_ONLY_ENV_VAR';

    afterEach(() => {
        delete process.env[key];
    });

    it('returns the value when set', () => {
        process.env[key] = 'value';

        expect(getEnv(key)).toEqual('value');
    });

    it('returns undefined for a missing variable without a default', () => {
        expect(getEnv(key)).toBeUndefined();
    });

    it('returns the default for a missing variable', () => {
        expect(getEnv(key, 'fallback')).toEqual('fallback');
    });

    it('falls back to the default when the variable is set but empty', () => {
        process.env[key] = '';

        expect(getEnv(key, 'fallback')).toEqual('fallback');
        expect(getEnv(key)).toBeUndefined();
    });
});
