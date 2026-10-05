import {afterEach, beforeAll, describe, expect, it, vi} from 'vitest';
import {act, renderHook, waitFor} from '@testing-library/react';
import {getFileAttachments} from './messageAttachments';
import {usePendingAttachments} from './usePendingAttachments';

const upload = vi.hoisted(() => ({
    multipartUpload: vi.fn(),
}));
vi.mock('@/lib/api/upload', () => upload);

beforeAll(() => {
    URL.createObjectURL ??= () => 'blob:preview';
    URL.revokeObjectURL ??= () => {};
});

afterEach(() => {
    upload.multipartUpload.mockReset();
});

describe('getFileAttachments', () => {
    it('reads the file attachments only', () => {
        expect(
            getFileAttachments([
                {
                    type: 'file',
                    content: JSON.stringify({
                        id: 'f1',
                        name: 'a.pdf',
                        type: 'application/pdf',
                        size: 3,
                        url: 'https://s3/a.pdf',
                    }),
                },
                {type: 'annotation', content: '{"name": "x"}'},
                {type: 'file', content: 'not json'},
            ])
        ).toEqual([
            {
                id: 'f1',
                name: 'a.pdf',
                type: 'application/pdf',
                size: 3,
                url: 'https://s3/a.pdf',
            },
        ]);
        expect(getFileAttachments(undefined)).toEqual([]);
    });
});

describe('usePendingAttachments', () => {
    it('uploads the files and exposes the completed uploads', async () => {
        let finish!: (v: unknown) => void;
        upload.multipartUpload.mockImplementation(
            (
                _file: File,
                {
                    onProgress,
                }: {onProgress: (p: {loaded: number; total: number}) => void}
            ) => {
                onProgress({loaded: 5, total: 10});

                return new Promise(r => (finish = r));
            }
        );
        const {result} = renderHook(() => usePendingAttachments());

        act(() =>
            result.current.add([
                new File(['0123456789'], 'a.txt', {type: 'text/plain'}),
            ])
        );
        expect(result.current.uploading).toBe(true);
        expect(result.current.items[0]).toMatchObject({
            name: 'a.txt',
            progress: 0.5,
            status: 'uploading',
        });
        expect(result.current.inputs).toEqual([]);

        const multipart = {uploadId: 'u1', parts: [{ETag: 'e', PartNumber: 1}]};
        await act(async () => finish(multipart));
        expect(result.current.uploading).toBe(false);
        expect(result.current.inputs).toEqual([{type: 'file', multipart}]);

        act(() => result.current.clear());
        expect(result.current.items).toEqual([]);
    });

    it('reports failures and aborts removed uploads', async () => {
        const signals: AbortSignal[] = [];
        upload.multipartUpload
            .mockImplementationOnce(() => Promise.reject(new Error('boom')))
            .mockImplementationOnce(
                (_file: File, {signal}: {signal: AbortSignal}) => {
                    signals.push(signal);

                    return new Promise(() => {});
                }
            );
        const {result} = renderHook(() => usePendingAttachments());

        act(() =>
            result.current.add([
                new File(['x'], 'bad.png', {type: 'image/png'}),
                new File(['y'], 'slow.bin'),
            ])
        );
        await waitFor(() => expect(result.current.failed).toBe(true));
        expect(result.current.items[0]).toMatchObject({
            status: 'error',
            error: 'boom',
        });

        act(() => result.current.remove(result.current.items[1].key));
        expect(signals[0].aborted).toBe(true);
        expect(result.current.items).toHaveLength(1);
    });
});
