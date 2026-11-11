import {afterEach, describe, expect, it} from 'vitest';
import {
    decodeShareTokens,
    getShareTokens,
    getShareTokensHeader,
    MAX_SHARE_TOKENS,
    storeShareToken,
} from './shareTokens';
import {isApiUrl} from './http';

describe('shareTokens', () => {
    afterEach(() => {
        document.cookie = 'shareTokens=; path=/; max-age=0';
    });

    it('keeps the most recent token first, once', () => {
        expect(getShareTokensHeader()).toBeUndefined();
        storeShareToken('aaa');
        storeShareToken('bbb');
        storeShareToken('aaa');
        expect(getShareTokens()).toEqual(['aaa', 'bbb']);
        expect(getShareTokensHeader()).toBe('aaa,bbb');
    });

    it('keeps the most recent tokens only', () => {
        for (let i = 0; i < MAX_SHARE_TOKENS + 5; i++) {
            storeShareToken(`t${i}`);
        }
        const tokens = getShareTokens();
        expect(tokens).toHaveLength(MAX_SHARE_TOKENS);
        expect(tokens[0]).toBe(`t${MAX_SHARE_TOKENS + 4}`);
        expect(tokens).not.toContain('t0');
    });

    it('ignores what is not a token', () => {
        expect(decodeShareTokens(undefined)).toEqual([]);
        expect(decodeShareTokens(' abc , ,a;b,' + 'x'.repeat(65))).toEqual([
            'abc',
        ]);
    });

    it('sends the tokens to the API only', () => {
        expect(
            isApiUrl(
                'https://api.example/publications/1',
                'https://api.example'
            )
        ).toBe(true);
        expect(
            isApiUrl('https://s3.example/bucket/key', 'https://api.example')
        ).toBe(false);
        expect(isApiUrl('https://api.example/x', '/api')).toBe(false);
        expect(isApiUrl(`${window.location.origin}/api/x`, '/api')).toBe(true);
    });
});
