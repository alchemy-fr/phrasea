import {generatePublicUrl} from '../../src/resourceResolver';

describe('generatePublicUrl', () => {
    it('builds an /assets URL from PUBLIC_URL', () => {
        expect(generatePublicUrl('a/b.jpg', 'fs_test')).toEqual(
            'http://indexer.test/assets/?path=a%2Fb.jpg&source=fs_test'
        );
    });

    it('percent-encodes slashes and plus-encodes spaces', () => {
        expect(
            generatePublicUrl('Dossier Accentué/Été 2024.txt', 'fs_test')
        ).toEqual(
            'http://indexer.test/assets/?path=Dossier+Accentu%C3%A9%2F%C3%89t%C3%A9+2024.txt&source=fs_test'
        );
    });

    it('appends extra query parameters before path and source', () => {
        expect(
            generatePublicUrl('a/b.jpg', 's3_test', {bucket: 'bucket-a'})
        ).toEqual(
            'http://indexer.test/assets/?bucket=bucket-a&path=a%2Fb.jpg&source=s3_test'
        );
    });

    it('lets path and source win over a caller-supplied value', () => {
        expect(
            generatePublicUrl('real-path', 'real-source', {
                path: 'overridden',
                source: 'overridden',
            })
        ).toEqual(
            'http://indexer.test/assets/?path=real-path&source=real-source'
        );
    });
});
