import {describe, expect, it} from 'vitest';
import {
    detectImportFormat,
    IMPORT_PREVIEW_MAX_ERRORS,
    IMPORT_PREVIEW_MAX_SIZE,
    parseImportContent,
    previewImportFile,
} from './parseImportFile';

describe('detectImportFormat', () => {
    it.each([
        ['assets.json', 'json'],
        ['ASSETS.JSON', 'json'],
        ['export.ndjson', 'ndjson'],
        ['export.v2.jsonl', 'ndjson'],
        ['assets.csv', null],
        ['json', null],
        ['', null],
    ])('%s → %s', (name, expected) => {
        expect(detectImportFormat(name)).toBe(expected);
    });
});

describe('parseImportContent (ndjson)', () => {
    it('counts the entries and ignores blank lines', () => {
        const preview = parseImportContent(
            [
                '{"sourceUrl": "https://example.com/a.jpg"}',
                '',
                '   ',
                '{"name": "B", "tags": ["x"]}',
                '',
            ].join('\n'),
            'ndjson'
        );

        expect(preview.count).toBe(2);
        expect(preview.errors).toEqual([]);
        expect(preview.errorCount).toBe(0);
        expect(preview.truncated).toBe(false);
        expect(preview.fatal).toBeUndefined();
    });

    it('reports errors with the file line', () => {
        const preview = parseImportContent(
            [
                '{"name": "ok"}',
                '',
                '{not json',
                '[1, 2]',
                '{"tags": ["only tags"]}',
                '{"name": "x", "collection": "c1", "path": "A/B"}',
                '{"renditions": {}}',
            ].join('\r\n'),
            'ndjson'
        );

        expect(preview.count).toBe(6);
        expect(preview.errors.map(e => [e.line, e.code])).toEqual([
            [3, 'invalid_line'],
            [4, 'not_object'],
            [5, 'missing_source'],
            [6, 'collection_and_path'],
            [7, 'missing_source'],
        ]);
        expect(preview.errors[0].message).toBeTruthy();
    });

    it('keeps the first errors only, and counts them all', () => {
        const lines = Array.from(
            {length: IMPORT_PREVIEW_MAX_ERRORS + 5},
            () => '{}'
        );

        const preview = parseImportContent(lines.join('\n'), 'ndjson');

        expect(preview.errors).toHaveLength(IMPORT_PREVIEW_MAX_ERRORS);
        expect(preview.errorCount).toBe(IMPORT_PREVIEW_MAX_ERRORS + 5);
    });

    it('drops the cut last line of a truncated read', () => {
        const preview = parseImportContent(
            '{"name": "a"}\n{"name": "b"}\n{"name": "c',
            'ndjson',
            {truncated: true}
        );

        expect(preview.count).toBe(2);
        expect(preview.errors).toEqual([]);
        expect(preview.truncated).toBe(true);
    });

    it('ignores a byte order mark', () => {
        expect(parseImportContent('﻿{"name": "a"}', 'ndjson').errors).toEqual(
            []
        );
    });
});

describe('parseImportContent (json)', () => {
    it('reads an array of entries, numbered from 1', () => {
        const preview = parseImportContent(
            JSON.stringify([
                {sourceUrl: 'https://example.com/a.jpg'},
                {key: 'k2'},
                'nope',
            ]),
            'json'
        );

        expect(preview.count).toBe(3);
        expect(preview.errors.map(e => [e.line, e.code])).toEqual([
            [2, 'missing_source'],
            [3, 'not_object'],
        ]);
    });

    it('reads an object with an "assets" array', () => {
        const preview = parseImportContent(
            JSON.stringify({
                assets: [{previewUrl: 'https://example.com/p.jpg'}],
            }),
            'json'
        );

        expect(preview.count).toBe(1);
        expect(preview.fatal).toBeUndefined();
    });

    it('rejects an invalid document', () => {
        const preview = parseImportContent('[{"name": "a"},', 'json');

        expect(preview.fatal?.code).toBe('invalid_json');
        expect(preview.count).toBeUndefined();
    });

    it('rejects a document of another shape', () => {
        expect(parseImportContent('{"items": []}', 'json').fatal?.code).toBe(
            'invalid_structure'
        );
        expect(parseImportContent('42', 'json').fatal?.code).toBe(
            'invalid_structure'
        );
    });

    it('does not parse a truncated document', () => {
        const preview = parseImportContent('[{"name": "a"}', 'json', {
            truncated: true,
        });

        expect(preview.count).toBeUndefined();
        expect(preview.fatal).toBeUndefined();
        expect(preview.truncated).toBe(true);
    });
});

describe('previewImportFile', () => {
    it('reads a small file entirely', async () => {
        const file = new File(['{"name": "a"}\n{"name": "b"}\n'], 'a.ndjson');

        const preview = await previewImportFile(file, 'ndjson');

        expect(preview.count).toBe(2);
        expect(preview.truncated).toBe(false);
    });

    it('only reads the beginning of a large file', async () => {
        const line = '{"name": "an asset with a rather long name"}\n';
        const repeat = Math.ceil((IMPORT_PREVIEW_MAX_SIZE + 1) / line.length);
        const file = new File([line.repeat(repeat)], 'big.ndjson');

        const preview = await previewImportFile(file, 'ndjson');

        expect(preview.truncated).toBe(true);
        expect(preview.count).toBeLessThan(repeat);
        expect(preview.count).toBeGreaterThan(0);
        expect(preview.errors).toEqual([]);
    });

    it('does not read a large JSON file', async () => {
        const file = new File(
            ['[', ' '.repeat(IMPORT_PREVIEW_MAX_SIZE), ']'],
            'big.json'
        );

        const preview = await previewImportFile(file, 'json');

        expect(preview.truncated).toBe(true);
        expect(preview.count).toBeUndefined();
    });
});
