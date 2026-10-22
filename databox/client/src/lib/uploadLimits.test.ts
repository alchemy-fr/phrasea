import {describe, expect, it} from 'vitest';
import {createI18n} from '@/i18n';
import {
    checkUploadFile,
    describeUploadLimits,
    describeUploadRejection,
    ENTITY_IMAGE_LIMITS,
    ENTITY_IMAGE_MAX_SIZE,
    formatAcceptedTypes,
    LOGO_LIMITS,
    LOGO_MAX_SIZE,
    SVG_MAX_SIZE,
    TERMS_PDF_LIMITS,
    TERMS_PDF_MAX_SIZE,
} from './uploadLimits';

function fileOf(name: string, type: string, size: number): File {
    const file = new File(['x'], name, {type});
    Object.defineProperty(file, 'size', {value: size});

    return file;
}

const MiB = 1024 * 1024;

describe('uploadLimits', () => {
    it('copies the API limits', () => {
        expect(ENTITY_IMAGE_MAX_SIZE).toBe(20 * MiB);
        expect(LOGO_MAX_SIZE).toBe(5 * MiB);
        expect(SVG_MAX_SIZE).toBe(100 * 1024);
        expect(TERMS_PDF_MAX_SIZE).toBe(20 * MiB);
    });

    it('accepts a file within the limits', () => {
        expect(
            checkUploadFile(
                fileOf('a.png', 'image/png', 20 * MiB),
                ENTITY_IMAGE_LIMITS
            )
        ).toBeNull();
        expect(
            checkUploadFile(
                fileOf('a.svg', 'image/svg+xml', 100 * 1024),
                LOGO_LIMITS
            )
        ).toBeNull();
        expect(
            checkUploadFile(
                fileOf('terms.pdf', 'application/pdf', MiB),
                TERMS_PDF_LIMITS
            )
        ).toBeNull();
    });

    it('refuses a file over the limit', () => {
        expect(
            checkUploadFile(
                fileOf('a.jpg', 'image/jpeg', 20 * MiB + 1),
                ENTITY_IMAGE_LIMITS
            )
        ).toEqual({reason: 'size', maxSize: 20 * MiB});
        expect(
            checkUploadFile(
                fileOf('a.webp', 'image/webp', 6 * MiB),
                LOGO_LIMITS
            )
        ).toEqual({reason: 'size', maxSize: 5 * MiB});
    });

    it('applies the SVG limit to an SVG file', () => {
        expect(
            checkUploadFile(
                fileOf('a.svg', 'image/svg+xml', 100 * 1024 + 1),
                ENTITY_IMAGE_LIMITS
            )
        ).toEqual({reason: 'size', maxSize: 100 * 1024});
        // No type given by the browser: the extension tells it
        expect(
            checkUploadFile(fileOf('a.svg', '', 200 * 1024), LOGO_LIMITS)
        ).toEqual({reason: 'size', maxSize: 100 * 1024});
    });

    it('refuses a type out of the accepted ones', () => {
        expect(
            checkUploadFile(
                fileOf('a.tiff', 'image/tiff', 10),
                ENTITY_IMAGE_LIMITS
            )
        ).toEqual({reason: 'type', maxSize: 20 * MiB});
        expect(
            checkUploadFile(fileOf('a.png', 'image/png', 10), TERMS_PDF_LIMITS)
        ).toEqual({reason: 'type', maxSize: 20 * MiB});
        expect(
            checkUploadFile(fileOf('noext', '', 10), ENTITY_IMAGE_LIMITS)
        ).toEqual({reason: 'type', maxSize: 20 * MiB});
    });

    it('supports wildcards and extensions in accept', () => {
        const limits = {accept: 'image/*,.txt', maxSize: 100};
        expect(
            checkUploadFile(fileOf('a.tiff', 'image/tiff', 10), limits)
        ).toBeNull();
        expect(checkUploadFile(fileOf('a.TXT', '', 10), limits)).toBeNull();
        expect(
            checkUploadFile(fileOf('a.pdf', 'application/pdf', 10), limits)
        ).toEqual({reason: 'type', maxSize: 100});
    });

    it('describes the limits and the rejections', () => {
        const i18n = createI18n('en');
        expect(formatAcceptedTypes(ENTITY_IMAGE_LIMITS.accept, 'en')).toBe(
            'JPEG, PNG, WebP, GIF, or SVG'
        );
        expect(describeUploadLimits(i18n.t, ENTITY_IMAGE_LIMITS, 'en')).toBe(
            'JPEG, PNG, WebP, GIF, or SVG · 20 MiB max (SVG: 100 KiB)'
        );
        expect(describeUploadLimits(i18n.t, TERMS_PDF_LIMITS, 'en')).toBe(
            'PDF · 20 MiB max'
        );

        const big = fileOf('big.png', 'image/png', 6 * MiB);
        expect(
            describeUploadRejection(
                i18n.t,
                big,
                checkUploadFile(big, LOGO_LIMITS)!,
                LOGO_LIMITS,
                'en'
            )
        ).toBe('"big.png" is too large (6 MiB): the limit is 5 MiB.');
    });
});
