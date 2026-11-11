import {describe, expect, it} from 'vitest';
import {getUploadLinkUrl, uploadLinkRequestOptions} from './uploadLinks';

describe('uploadLinkRequestOptions', () => {
    it('sends the token of the link instead of the session', () => {
        expect(uploadLinkRequestOptions('abc')).toEqual({
            anonymous: true,
            headers: {Authorization: 'UploadLink abc'},
        });
    });
});

describe('getUploadLinkUrl', () => {
    it('points to the page of the link', () => {
        expect(getUploadLinkUrl('abc', 'https://uploader.example')).toBe(
            'https://uploader.example/l/abc'
        );
    });
});
