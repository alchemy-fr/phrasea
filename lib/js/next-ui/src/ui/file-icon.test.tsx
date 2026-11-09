import {render, screen} from '@testing-library/react';
import {describe, expect, it} from 'vitest';
import {FileFamilyIcon, FileTypeChip} from './file-icon';

describe('FileFamilyIcon', () => {
    it.each([
        ['image/jpeg', 'image'],
        ['application/x-photoshop', 'image'],
        ['video/mp4', 'video'],
        ['audio/mpeg', 'audio'],
        ['application/pdf', 'document'],
        ['application/zip', 'other'],
        [undefined, 'other'],
    ])('%s → %s', (mimeType, family) => {
        render(<FileFamilyIcon mimeType={mimeType} />);
        expect(screen.getByTestId('file-family-icon').dataset.family).toBe(
            family
        );
    });
});

describe('FileTypeChip', () => {
    it('shows the extension, or the MIME subtype without any', () => {
        const {rerender} = render(
            <FileTypeChip mimeType="image/jpeg" extension="jpg" />
        );
        expect(screen.getByText('JPG')).toBeTruthy();
        rerender(<FileTypeChip mimeType="image/png" />);
        expect(screen.getByText('PNG')).toBeTruthy();
    });

    it('shows nothing without type', () => {
        const {container} = render(<FileTypeChip />);
        expect(container.firstChild).toBeNull();
    });
});
