import {describe, expect, it} from 'vitest';
import {FileFamily, getFileFamily} from './mime';

describe('getFileFamily', () => {
    it.each([
        ['image/jpeg', FileFamily.Image],
        ['application/x-photoshop', FileFamily.Image],
        ['audio/mpeg', FileFamily.Audio],
        ['video/mp4', FileFamily.Video],
        ['application/mxf', FileFamily.Video],
        ['application/pdf', FileFamily.Document],
        ['text/csv; charset=utf-8', FileFamily.Document],
        ['IMAGE/PNG', FileFamily.Image],
        ['application/zip', FileFamily.Other],
        ['', FileFamily.Other],
        [undefined, FileFamily.Other],
    ])('%s → %s', (mime, expected) => {
        expect(getFileFamily(mime)).toBe(expected);
    });
});
