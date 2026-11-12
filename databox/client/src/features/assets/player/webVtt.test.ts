import {describe, expect, it} from 'vitest';
import {activeCueIndex, parseWebVtt, type WebVttCue} from './webVtt';

describe('parseWebVtt', () => {
    it('reads the cues of a minimal file', () => {
        expect(
            parseWebVtt(
                'WEBVTT\n\n00:00:01.000 --> 00:00:04.500\nHello\n\n00:00:05.000 --> 00:00:07.250\nWorld\n'
            )
        ).toEqual([
            {start: 1, end: 4.5, text: 'Hello'},
            {start: 5, end: 7.25, text: 'World'},
        ]);
    });

    it('reads the times with or without hours', () => {
        expect(
            parseWebVtt(
                'WEBVTT\n\n01:02.500 --> 01:03.000\nShort\n\n123:00:00.000 --> 123:00:01.000\nLong'
            )
        ).toEqual([
            {start: 62.5, end: 63, text: 'Short'},
            {start: 442800, end: 442801, text: 'Long'},
        ]);
    });

    it('ignores the settings, the identifiers and the header text', () => {
        expect(
            parseWebVtt(
                'WEBVTT - Interview\nKind: captions\n\nintro\n00:00.000 --> 00:02.000 align:start position:10% line:0\nHi\n\n2\n00:02.000 --> 00:03.000\nThere'
            )
        ).toEqual([
            {start: 0, end: 2, text: 'Hi'},
            {start: 2, end: 3, text: 'There'},
        ]);
    });

    it('skips the NOTE, STYLE and REGION blocks', () => {
        expect(
            parseWebVtt(
                [
                    'WEBVTT',
                    'NOTE a comment\n00:00.000 --> 00:01.000\nnot a cue',
                    'STYLE\n::cue { color: red }',
                    'REGION\nid:fred width:40%',
                    'NOTE',
                    '00:01.000 --> 00:02.000\nCue',
                ].join('\n\n')
            )
        ).toEqual([{start: 1, end: 2, text: 'Cue'}]);
    });

    it('keeps the lines of a cue', () => {
        expect(
            parseWebVtt(
                'WEBVTT\n\n00:00.000 --> 00:01.000\n  First line \nSecond line\n\n\n\n00:01.000 --> 00:02.000\nNext'
            )
        ).toEqual([
            {start: 0, end: 1, text: 'First line\nSecond line'},
            {start: 1, end: 2, text: 'Next'},
        ]);
    });

    it('drops the tags, the speaker of the first voice kept', () => {
        expect(
            parseWebVtt(
                'WEBVTT\n\n00:00.000 --> 00:03.000\n<v.loud Mary Smith><i>Hi</i> <c.yellow><b>there</b></c>,<00:00:01.500> <u>you</u></v>\n<v Bob>and <ruby>漢<rt>kan</rt></ruby> <lang en-GB>colour</lang>'
            )
        ).toEqual([
            {
                start: 0,
                end: 3,
                text: 'Hi there, you\nand 漢kan colour',
                voice: 'Mary Smith',
            },
        ]);
    });

    it('decodes the entities', () => {
        expect(
            parseWebVtt(
                'WEBVTT\n\n00:00.000 --> 00:01.000\nR&amp;D &lt;3 &gt; a&nbsp;b &#233;t&#xE9; &lrm;x&rlm; &unknown;'
            )[0].text
        ).toBe('R&D <3 > a b été ‎x‏ &unknown;');
    });

    it('reads a file with a BOM and CRLF line ends', () => {
        expect(
            parseWebVtt(
                '﻿WEBVTT\r\n\r\n00:00.000 --> 00:01.000\r\nOne\r\nTwo\r\n\r\n00:01.000 --> 00:02.000\rThree'
            )
        ).toEqual([
            {start: 0, end: 1, text: 'One\nTwo'},
            {start: 1, end: 2, text: 'Three'},
        ]);
    });

    it('has no cue without the header', () => {
        expect(parseWebVtt('00:00.000 --> 00:01.000\nHello')).toEqual([]);
        expect(
            parseWebVtt('WEBVTTX\n\n00:00.000 --> 00:01.000\nHello')
        ).toEqual([]);
        expect(parseWebVtt('')).toEqual([]);
    });

    it('skips the blocks without a valid timing, and the empty cues', () => {
        expect(
            parseWebVtt(
                'WEBVTT\n\n0:00.000 --> 00:01.000\nBad minutes\n\n00:00.00 --> 00:01.000\nBad millis\n\nJust text\n\n00:01.000 --> 00:02.000\n<i></i>\n\n00:02.000 --> 00:03.000\nGood'
            )
        ).toEqual([{start: 2, end: 3, text: 'Good'}]);
    });

    it('ignores the highlight markers on the timing lines, keeps them in the text', () => {
        expect(
            parseWebVtt(
                '[hl]WEBVTT[/hl]\n\n[hl]cue[/hl]-1\n00:[hl]01[/hl].000 --> 00:02.000\nThe [hl]cue[/hl] text\n\n00:02.000 --> 00:03.000\n<v [hl]Bob[/hl]>Hi'
            )
        ).toEqual([
            {start: 1, end: 2, text: 'The [hl]cue[/hl] text'},
            {start: 2, end: 3, text: 'Hi', voice: 'Bob'},
        ]);
    });

    it('keeps the order of the file', () => {
        expect(
            parseWebVtt(
                'WEBVTT\n\n00:05.000 --> 00:06.000\nLater\n\n00:01.000 --> 00:02.000\nEarlier'
            ).map(c => c.text)
        ).toEqual(['Later', 'Earlier']);
    });
});

describe('activeCueIndex', () => {
    const cues: WebVttCue[] = [
        {start: 1, end: 3, text: 'a'},
        {start: 4, end: 8, text: 'b'},
        {start: 6, end: 7, text: 'c'},
    ];

    it('finds the last cue on screen', () => {
        expect(activeCueIndex(cues, 0)).toBe(-1);
        expect(activeCueIndex(cues, 1)).toBe(0);
        expect(activeCueIndex(cues, 2.9)).toBe(0);
        expect(activeCueIndex(cues, 3)).toBe(-1);
        expect(activeCueIndex(cues, 3.5)).toBe(-1);
        expect(activeCueIndex(cues, 5)).toBe(1);
        expect(activeCueIndex(cues, 6.5)).toBe(2);
        expect(activeCueIndex(cues, 7.5)).toBe(1);
        expect(activeCueIndex(cues, 8)).toBe(-1);
        expect(activeCueIndex([], 1)).toBe(-1);
    });
});
