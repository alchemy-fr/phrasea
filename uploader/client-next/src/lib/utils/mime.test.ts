import {describe, expect, it} from 'vitest';
import {
    FileKind,
    getFileKind,
    getMimeTypeFromFile,
    toDropzoneAccept,
} from './mime';

describe('getFileKind', () => {
    it('reads the main type', () => {
        expect(getFileKind('image/png')).toBe(FileKind.Image);
        expect(getFileKind('video/mp4')).toBe(FileKind.Video);
        expect(getFileKind('audio/wav')).toBe(FileKind.Audio);
        expect(getFileKind('application/pdf')).toBe(FileKind.Document);
        expect(getFileKind(undefined)).toBe(FileKind.Unknown);
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
