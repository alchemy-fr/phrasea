import {afterEach, describe, expect, it} from 'vitest';
import {
    decodeShareTokens,
    getShareTokens,
    getShareTokensHeader,
    MAX_SHARE_TOKENS,
    storeShareToken,
} from './shareTokens';

function shareTokenCookies(): string[] {
    return document.cookie
        .split(';')
        .map(c => c.trim().split('=')[0])
        .filter(name => name.startsWith('shareTokens_'));
}

describe('shareTokens', () => {
    afterEach(() => {
        shareTokenCookies().forEach(name => {
            document.cookie = `${name}=; path=/; max-age=0`;
        });
    });

    it('keeps the most recent token first, once, under the id and the slug', () => {
        const publication = {id: 'p1', slug: 'my-slug'};
        expect(getShareTokensHeader(publication)).toBeUndefined();
        storeShareToken(publication, 'aaa');
        storeShareToken(publication, 'bbb');
        storeShareToken(publication, 'aaa');
        expect(getShareTokens('p1')).toEqual(['aaa', 'bbb']);
        expect(getShareTokens('my-slug')).toEqual(['aaa', 'bbb']);
        expect(getShareTokensHeader(publication)).toBe('aaa,bbb');
    });

    it('keeps the tokens of each publication apart', () => {
        storeShareToken({id: 'p1'}, 'aaa');
        expect(getShareTokens('p2')).toEqual([]);
        expect(getShareTokensHeader('p2')).toBeUndefined();
        expect(getShareTokensHeader({id: 'p2', slug: 'other'})).toBeUndefined();
        expect(getShareTokensHeader('p1')).toBe('aaa');
    });

    it('writes the cookie of the id only without a slug', () => {
        storeShareToken({id: 'p1', slug: null}, 'aaa');
        expect(shareTokenCookies()).toEqual(['shareTokens_p1']);
    });

    it('merges the tokens kept under the id and under the slug', () => {
        storeShareToken({id: 'p1'}, 'aaa');
        storeShareToken({id: 'p1', slug: 'my-slug'}, 'bbb');
        expect(getShareTokens({id: 'p1', slug: 'my-slug'})).toEqual([
            'bbb',
            'aaa',
        ]);
    });

    it('keeps the most recent tokens of a publication only', () => {
        expect(MAX_SHARE_TOKENS).toBe(5);
        for (let i = 0; i < MAX_SHARE_TOKENS + 3; i++) {
            storeShareToken({id: 'p1'}, `t${i}`);
        }
        const tokens = getShareTokens('p1');
        expect(tokens).toHaveLength(MAX_SHARE_TOKENS);
        expect(tokens[0]).toBe(`t${MAX_SHARE_TOKENS + 2}`);
        expect(tokens).not.toContain('t0');
    });

    it('ignores what is not a token', () => {
        expect(decodeShareTokens(undefined)).toEqual([]);
        expect(decodeShareTokens(' abc , ,a;b,' + 'x'.repeat(65))).toEqual([
            'abc',
        ]);
    });
});
