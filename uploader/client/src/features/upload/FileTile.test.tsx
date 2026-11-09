import {beforeEach, describe, expect, it, vi} from 'vitest';
import {fireEvent, render, screen} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {createI18n} from '@/i18n';
import {FileTile, PREVIEW_MAX_SIZE} from './FileTile';

function makeFile(name: string, type: string, size: number): File {
    const file = new File([''], name, {type});
    Object.defineProperty(file, 'size', {value: size});

    return file;
}

function renderTile(file: File) {
    render(
        <I18nextProvider i18n={createI18n('en')}>
            <ul>
                <FileTile file={file} />
            </ul>
        </I18nextProvider>
    );
}

describe('FileTile', () => {
    beforeEach(() => {
        URL.createObjectURL = vi.fn(() => 'blob:preview');
        URL.revokeObjectURL = vi.fn();
    });

    it('previews a small image', () => {
        renderTile(makeFile('a.jpg', 'image/jpeg', 1024));

        expect(screen.getByTestId('file-preview')).toBeTruthy();
        expect(screen.queryByTestId('file-family-icon')).toBeNull();
    });

    it('shows the image icon of a too large image', () => {
        renderTile(makeFile('a.jpg', 'image/jpeg', PREVIEW_MAX_SIZE + 1));

        expect(screen.queryByTestId('file-preview')).toBeNull();
        expect(screen.getByTestId('file-family-icon').dataset.family).toBe(
            'image'
        );
    });

    it('shows the image icon when the browser cannot decode the image', () => {
        renderTile(makeFile('a.psd', '', 1024));

        fireEvent.error(screen.getByTestId('file-preview'));

        expect(screen.queryByTestId('file-preview')).toBeNull();
        expect(screen.getByTestId('file-family-icon').dataset.family).toBe(
            'image'
        );
    });

    it('shows the icon of the family of other files', () => {
        renderTile(makeFile('a.mp4', 'video/mp4', 1024));

        expect(screen.getByTestId('file-family-icon').dataset.family).toBe(
            'video'
        );
    });
});
