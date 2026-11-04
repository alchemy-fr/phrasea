import {describe, expect, it} from 'vitest';
import {FileKind, getFileKind} from './mime';

describe('getFileKind', () => {
    it.each([
        ['image/jpeg', FileKind.Image],
        ['IMAGE/PNG', FileKind.Image],
        ['audio/mpeg', FileKind.Audio],
        ['video/mp4', FileKind.Video],
        ['application/pdf', FileKind.Document],
        ['text/csv; charset=utf-8', FileKind.Document],
        ['font/woff2', FileKind.Unknown],
        ['', FileKind.Unknown],
        [undefined, FileKind.Unknown],
    ])('%s → %s', (mime, expected) => {
        expect(getFileKind(mime)).toBe(expected);
    });
});
