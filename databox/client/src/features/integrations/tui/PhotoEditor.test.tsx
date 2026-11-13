import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {createRef} from 'react';
import {act, cleanup, render, screen, waitFor} from '@testing-library/react';
import {PhotoEditor, type PhotoEditorInstance} from './PhotoEditor';

vi.mock('react-i18next', () => ({
    useTranslation: () => ({
        t: (_key: string, defaultValue: string) => defaultValue,
        i18n: {language: 'en'},
    }),
}));

vi.mock('tui-image-editor/dist/tui-image-editor.css', () => ({}));

const editor = vi.hoisted(() => {
    const destroy = vi.fn();
    const options: Record<string, unknown>[] = [];
    const ImageEditor = vi.fn(function (
        this: Record<string, unknown>,
        _el: HTMLElement,
        opts: Record<string, unknown>
    ) {
        options.push(opts);
        this.destroy = destroy;
        this.toDataURL = () => '';
    });

    return {ImageEditor, destroy, options};
});

vi.mock('tui-image-editor', () => ({default: editor.ImageEditor}));

const imageUrl =
    'https://s3.example.com/bucket/IMG_0800_p.jpg?X-Amz-Signature=abc';

function loadedPath(): unknown {
    const includeUI = editor.options[0].includeUI as {
        loadImage: {path: string; name: string};
    };

    return includeUI.loadImage.path;
}

function renderEditor(url = imageUrl) {
    const editorRef = createRef<PhotoEditorInstance | null>();

    return render(
        <PhotoEditor url={url} name="IMG_0800_p.jpg" editorRef={editorRef} />
    );
}

describe('PhotoEditor', () => {
    let fetchMock: ReturnType<typeof vi.fn>;
    let createObjectURL: ReturnType<typeof vi.fn>;
    let revokeObjectURL: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        editor.ImageEditor.mockClear();
        editor.destroy.mockClear();
        editor.options.length = 0;

        fetchMock = vi.fn(async () => ({
            ok: true,
            status: 200,
            blob: async () => new Blob(['jpeg'], {type: 'image/jpeg'}),
        }));
        vi.stubGlobal('fetch', fetchMock);

        let n = 0;
        createObjectURL = vi.fn(() => `blob:image-${++n}`);
        revokeObjectURL = vi.fn();
        // jsdom has neither
        Object.assign(URL, {createObjectURL, revokeObjectURL});
    });

    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
        Reflect.deleteProperty(URL, 'createObjectURL');
        Reflect.deleteProperty(URL, 'revokeObjectURL');
    });

    it('hands the editor a blob URL fetched through CORS outside the HTTP cache, revoked once destroyed', async () => {
        const {unmount} = renderEditor();

        await waitFor(() => expect(editor.ImageEditor).toHaveBeenCalledOnce());
        expect(fetchMock).toHaveBeenCalledWith(
            imageUrl,
            expect.objectContaining({
                mode: 'cors',
                credentials: 'omit',
                cache: 'no-store',
            })
        );
        expect(loadedPath()).toBe('blob:image-1');
        expect(revokeObjectURL).not.toHaveBeenCalled();

        unmount();
        expect(editor.destroy).toHaveBeenCalledOnce();
        expect(revokeObjectURL).toHaveBeenCalledWith('blob:image-1');
        expect(editor.destroy.mock.invocationCallOrder[0]).toBeLessThan(
            revokeObjectURL.mock.invocationCallOrder[0]
        );
    });

    it('shows an error and creates no editor when the image cannot be fetched', async () => {
        fetchMock.mockResolvedValueOnce({ok: false, status: 403});
        renderEditor();

        expect(
            await screen.findByText('The photo editor could not be loaded')
        ).toBeTruthy();
        expect(screen.getByText(/HTTP 403/)).toBeTruthy();
        expect(editor.ImageEditor).not.toHaveBeenCalled();
        expect(createObjectURL).not.toHaveBeenCalled();
    });

    it('creates no editor and leaves no blob URL when unmounted while fetching', async () => {
        let resolveFetch!: (value: unknown) => void;
        // The response still arrives, as if the abort came too late
        fetchMock.mockImplementationOnce(
            () =>
                new Promise(resolve => {
                    resolveFetch = resolve;
                })
        );
        const {unmount} = renderEditor();
        await waitFor(() => expect(fetchMock).toHaveBeenCalledOnce());
        const signal = (fetchMock.mock.calls[0][1] as RequestInit).signal!;

        unmount();
        expect(signal.aborted).toBe(true);

        resolveFetch({
            ok: true,
            status: 200,
            blob: async () => new Blob(['jpeg'], {type: 'image/jpeg'}),
        });
        await act(() => new Promise(resolve => setTimeout(resolve, 10)));

        expect(editor.ImageEditor).not.toHaveBeenCalled();
        expect(createObjectURL).toHaveBeenCalledOnce();
        expect(revokeObjectURL).toHaveBeenCalledWith('blob:image-1');
    });

    it('passes a data URL as is, without fetching it', async () => {
        const dataUrl = 'data:image/png;base64,iVBORw0KGgo=';
        renderEditor(dataUrl);

        await waitFor(() => expect(editor.ImageEditor).toHaveBeenCalledOnce());
        expect(fetchMock).not.toHaveBeenCalled();
        expect(createObjectURL).not.toHaveBeenCalled();
        expect(loadedPath()).toBe(dataUrl);
    });
});
