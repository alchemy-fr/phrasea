import {describe, expect, it} from 'vitest';
import {
    describeAllowedTypes,
    FileFamily,
    FileKind,
    getFileFamily,
    getFileKind,
    getMimeTypeFromFile,
    toDropzoneAccept,
} from './mime';

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
        [null, FileKind.Unknown],
    ])('%s → %s', (mime, expected) => {
        expect(getFileKind(mime)).toBe(expected);
    });
});

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

describe('getMimeTypeFromFile', () => {
    it('prefers the type given by the browser', () => {
        expect(
            getMimeTypeFromFile(new File([''], 'a.psd', {type: 'image/x'}))
        ).toBe('image/x');
    });

    it('falls back to the extension', () => {
        expect(getMimeTypeFromFile(new File([''], 'a.PSD'))).toBe(
            'image/vnd.adobe.photoshop'
        );
        expect(getMimeTypeFromFile(new File([''], 'a.unknown'))).toBe(
            undefined
        );
    });
});

describe('toDropzoneAccept', () => {
    it('accepts everything without restriction', () => {
        expect(toDropzoneAccept({})).toBeUndefined();
        expect(toDropzoneAccept({'*/*': []})).toBeUndefined();
    });

    it('keeps the allowed types', () => {
        expect(toDropzoneAccept({'image/*': ['.jpg']})).toEqual({
            'image/*': ['.jpg'],
        });
    });
});

describe('describeAllowedTypes', () => {
    it('lists the extensions, or the MIME type without any', () => {
        expect(
            describeAllowedTypes({
                'image/jpeg': ['.jpg', '.jpeg'],
                'image/pjpeg': ['.jpg'],
                'video/*': [],
            })
        ).toBe('JPG, JPEG, video/*');
    });

    it('describes nothing when every type is allowed', () => {
        expect(describeAllowedTypes({})).toBeUndefined();
        expect(describeAllowedTypes({'*/*': []})).toBeUndefined();
    });
});
