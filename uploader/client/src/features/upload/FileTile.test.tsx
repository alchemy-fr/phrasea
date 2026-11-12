import {beforeEach, describe, expect, it, vi} from 'vitest';
import {fireEvent, render, screen, waitFor} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {createI18n} from '@/i18n';
import {FileTile} from './FileTile';
import {PREVIEW_MAX_SIZE, PreviewCacheProvider} from './previewCache';
import {createThumbnail} from './thumbnail';

vi.mock('./thumbnail', () => ({createThumbnail: vi.fn()}));

function makeFile(name: string, type: string, size: number): File {
    const file = new File([''], name, {type});
    Object.defineProperty(file, 'size', {value: size});

    return file;
}

function Tiles({files, shown = true}: {files: File[]; shown?: boolean}) {
    return (
        <I18nextProvider i18n={createI18n('en')}>
            <PreviewCacheProvider files={files}>
                {shown ? (
                    <ul>
                        {files.map(file => (
                            <FileTile key={file.name} file={file} />
                        ))}
                    </ul>
                ) : null}
            </PreviewCacheProvider>
        </I18nextProvider>
    );
}

function renderTile(file: File) {
    return render(<Tiles files={[file]} />);
}

describe('FileTile', () => {
    beforeEach(() => {
        vi.mocked(createThumbnail)
            .mockReset()
            .mockImplementation(async file => {
                if (file.name.endsWith('.psd')) {
                    throw new Error('Cannot decode');
                }

                return new Blob([file.name]);
            });
        let n = 0;
        URL.createObjectURL = vi.fn(() => `blob:${++n}`);
        URL.revokeObjectURL = vi.fn();
    });

    it('previews a small image with its thumbnail', async () => {
        renderTile(makeFile('a.jpg', 'image/jpeg', 1024));

        expect(
            (await screen.findByTestId('file-preview')).getAttribute('src')
        ).toBe('blob:1');
        expect(screen.queryByTestId('file-family-icon')).toBeNull();
    });

    it('shows the image icon of a too large image', () => {
        renderTile(makeFile('a.jpg', 'image/jpeg', PREVIEW_MAX_SIZE + 1));

        expect(screen.queryByTestId('file-preview')).toBeNull();
        expect(screen.getByTestId('file-family-icon').dataset.family).toBe(
            'image'
        );
        expect(createThumbnail).not.toHaveBeenCalled();
    });

    it('shows the image icon when the browser cannot decode the image', async () => {
        const file = makeFile('a.psd', '', 1024);
        const {rerender} = render(<Tiles files={[file]} />);
        await waitFor(() => expect(createThumbnail).toHaveBeenCalled());

        rerender(<Tiles files={[file]} shown={false} />);
        rerender(<Tiles files={[file]} />);

        expect(screen.queryByTestId('file-preview')).toBeNull();
        expect(screen.getByTestId('file-family-icon').dataset.family).toBe(
            'image'
        );
        expect(createThumbnail).toHaveBeenCalledTimes(1);
    });

    it('shows the icon of the family of other files', () => {
        renderTile(makeFile('a.mp4', 'video/mp4', 1024));

        expect(screen.getByTestId('file-family-icon').dataset.family).toBe(
            'video'
        );
        expect(createThumbnail).not.toHaveBeenCalled();
    });

    it('shows at once the thumbnail of a tile hidden then shown again', async () => {
        const file = makeFile('a.jpg', 'image/jpeg', 1024);
        const {rerender} = render(<Tiles files={[file]} />);
        await screen.findByTestId('file-preview');

        rerender(<Tiles files={[file]} shown={false} />);
        rerender(<Tiles files={[file]} />);

        expect(screen.getByTestId('file-preview').getAttribute('src')).toBe(
            'blob:1'
        );
        expect(createThumbnail).toHaveBeenCalledTimes(1);
        expect(URL.revokeObjectURL).not.toHaveBeenCalled();
    });

    it('makes the thumbnails of the files not shown yet', async () => {
        const file = makeFile('a.jpg', 'image/jpeg', 1024);
        const {rerender} = render(<Tiles files={[file]} shown={false} />);
        await waitFor(() => expect(URL.createObjectURL).toHaveBeenCalled());

        rerender(<Tiles files={[file]} />);

        expect(screen.getByTestId('file-preview')).toBeTruthy();
    });

    it('releases the thumbnail of a file leaving the upload', async () => {
        const a = makeFile('a.jpg', 'image/jpeg', 1024);
        const b = makeFile('b.jpg', 'image/jpeg', 1024);
        const {rerender, unmount} = render(<Tiles files={[a, b]} />);
        await waitFor(() =>
            expect(screen.getAllByTestId('file-preview')).toHaveLength(2)
        );

        rerender(<Tiles files={[b]} />);
        expect(URL.revokeObjectURL).toHaveBeenCalledExactlyOnceWith('blob:1');

        unmount();
        expect(URL.revokeObjectURL).toHaveBeenLastCalledWith('blob:2');
    });

    it('removes a file already sent', () => {
        const onRemove = vi.fn();
        render(
            <I18nextProvider i18n={createI18n('en')}>
                <PreviewCacheProvider files={[]}>
                    <ul>
                        <FileTile
                            file={makeFile('a.mp4', 'video/mp4', 1024)}
                            status="done"
                            loaded={1024}
                            onRemove={onRemove}
                        />
                    </ul>
                </PreviewCacheProvider>
            </I18nextProvider>
        );

        fireEvent.click(screen.getByRole('button', {name: 'Remove a.mp4'}));

        expect(onRemove).toHaveBeenCalledOnce();
    });
});
