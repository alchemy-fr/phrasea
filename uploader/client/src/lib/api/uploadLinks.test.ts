import {describe, expect, it} from 'vitest';
import {
    getUploadLinkUrl,
    getUploadSessionId,
    uploadLinkRequestOptions,
} from './uploadLinks';

describe('uploadLinkRequestOptions', () => {
    it('sends the token of the link instead of the session', () => {
        expect(uploadLinkRequestOptions('abc', 'session-1')).toEqual({
            anonymous: true,
            headers: {
                'Authorization': 'UploadLink abc',
                'X-Upload-Session': 'session-1',
            },
        });
    });

    it('sends the upload session of the tab by default', () => {
        expect(
            uploadLinkRequestOptions('abc').headers?.['X-Upload-Session']
        ).toBe(getUploadSessionId());
    });
});

describe('getUploadSessionId', () => {
    it('keeps one UUID for the tab', () => {
        sessionStorage.clear();
        const id = getUploadSessionId();

        expect(id).toMatch(
            /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/
        );
        expect(getUploadSessionId()).toBe(id);

        sessionStorage.clear();
        expect(getUploadSessionId()).not.toBe(id);
    });
});

describe('getUploadLinkUrl', () => {
    it('points to the page of the link', () => {
        expect(getUploadLinkUrl('abc', 'https://uploader.example')).toBe(
            'https://uploader.example/l/abc'
        );
    });
});
