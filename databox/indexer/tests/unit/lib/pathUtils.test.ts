import {
    concatPath,
    escapePath,
    escapeSlashes,
    splitPath,
    stripSlashes,
} from '../../../src/lib/pathUtils';

describe('escapeSlashes / stripSlashes', () => {
    it('escapes every slash', () => {
        expect(escapeSlashes('a/b/c')).toEqual('a\\/b\\/c');
        expect(escapeSlashes('no-slash')).toEqual('no-slash');
        expect(escapeSlashes('')).toEqual('');
    });

    it('is reversed by stripSlashes', () => {
        for (const p of ['a/b/c', 'a', '/leading', 'trailing/', 'a//b']) {
            expect(stripSlashes(escapeSlashes(p))).toEqual(p);
        }
    });

    it('leaves unescaped slashes alone', () => {
        expect(stripSlashes('a/b')).toEqual('a/b');
    });
});

describe('escapePath', () => {
    it('escapes slashes so the segment survives splitPath', () => {
        expect(escapePath('Foo / Bar')).toEqual('Foo \\/ Bar');
        expect(splitPath(`a/${escapePath('b/c')}/d`)).toEqual([
            'a',
            'b/c',
            'd',
        ]);
    });

    it('replaces control characters with the replacement string', () => {
        expect(escapePath('a\x00b\x0Fc')).toEqual('a_b_c');
        expect(escapePath('a\x00b', '-')).toEqual('a-b');
    });

    it('strips the whole C0 range, not just \\x00-\\x0F', () => {
        expect(escapePath('a\x10b')).toEqual('a_b');
        expect(escapePath('a\x1Bb')).toEqual('a_b');
        expect(escapePath('a\x1Fb')).toEqual('a_b');
    });

    it('leaves plain text untouched', () => {
        expect(escapePath('Dossier Accentué')).toEqual('Dossier Accentué');
    });
});

describe('splitPath', () => {
    it('splits on slashes', () => {
        expect(splitPath('a/b/c')).toEqual(['a', 'b', 'c']);
    });

    it('drops empty segments', () => {
        expect(splitPath('/a//b/c/')).toEqual(['a', 'b', 'c']);
        expect(splitPath('')).toEqual([]);
        expect(splitPath('///')).toEqual([]);
    });

    it('keeps escaped slashes inside a segment', () => {
        expect(splitPath('a/b\\/c/d')).toEqual(['a', 'b/c', 'd']);
    });

    it('returns a single segment when there is no separator', () => {
        expect(splitPath('single')).toEqual(['single']);
    });
});

describe('concatPath', () => {
    it('joins and normalises both sides', () => {
        expect(concatPath('a', 'b')).toEqual('a/b');
        expect(concatPath('a/', '/b')).toEqual('a/b');
        expect(concatPath('/a/b/', 'c/d')).toEqual('a/b/c/d');
    });

    it('tolerates empty operands', () => {
        expect(concatPath('', 'b')).toEqual('b');
        expect(concatPath('a', '')).toEqual('a');
    });
});
