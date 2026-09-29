/**
 * Multipart upload engine shared by every client (databox, uploader, databox
 * Next.js).
 *
 * The API decides the part size and presigns every part when the upload is
 * created, so the file is then sent straight to the storage, several parts in
 * parallel, without asking the API for each part. Expired URLs are renewed
 * from the first unfinished part (`POST /uploads/{id}/parts`), which also
 * serves resuming an interrupted upload.
 *
 * This module has no runtime dependency on purpose: each client plugs its own
 * HTTP stack through a {@link MultipartUploadTransport} (see
 * `axiosMultipartUpload.ts` for axios based clients).
 */

export type UploadPart = {
    ETag: string;
    PartNumber: number;
};

export type MultipartUpload = {
    uploadId: string;
    parts: UploadPart[];
};

export type MultipartUploadProgress = {
    loaded: number;
    total: number;
    /** Between 0 and 1 */
    progress: number;
};

/**
 * Answer of `POST /uploads`: the part size decided by the server and the
 * presigned PUT URL of every part, keyed by part number.
 */
export type MultipartUploadInit = {
    id: string;
    chunkSize: number;
    urls: Record<string, string>;
};

/** Answer of `POST /uploads/{id}/parts` */
export type MultipartUploadPlan = {
    chunkSize: number;
    partCount: number;
    urls: Record<string, string>;
};

export type MultipartUploadTransport = {
    /** `POST /uploads` */
    createUpload(
        input: {filename: string; type: string; size: number},
        signal: AbortSignal
    ): Promise<MultipartUploadInit>;
    /** `POST /uploads/{id}/parts` with `{from}` */
    getPartUrls(
        uploadId: string,
        from: number,
        signal: AbortSignal
    ): Promise<MultipartUploadPlan>;
    /** PUT a part to its presigned URL (anonymously), resolves with its ETag */
    putPart(
        url: string,
        blob: Blob,
        options: {signal: AbortSignal; onProgress: (loaded: number) => void}
    ): Promise<string>;
    /** Whether a `putPart` failure means the presigned URL expired (HTTP 403) */
    isExpiredUrlError(error: unknown): boolean;
    /** Error thrown when the upload is aborted from outside */
    createAbortError?(): Error;
};

export type MultipartUploadOptions = {
    /** MIME type sent to the API, defaults to `file.type` */
    type?: string;
    /** Resume an upload created earlier (requires `uploadParts`) */
    uploadId?: string;
    /** Parts already uploaded for `uploadId` */
    uploadParts?: UploadPart[];
    /** Number of parts PUT in parallel */
    concurrency?: number;
    /** Aborts the whole upload */
    signal?: AbortSignal;
    onUploadInit?: (props: {uploadId: string}) => void;
    onPartUploaded?: (props: {
        uploadId: string;
        etag: string;
        partNumber: number;
    }) => void;
    onProgress?: (progress: MultipartUploadProgress) => void;
    /**
     * Receives the controller aborting the whole upload (every API call and
     * every part PUT).
     */
    receiveAbortController?: (abortController: AbortController) => void;
};

export const DEFAULT_UPLOAD_CONCURRENCY = 3;

export function getPartCount(size: number, chunkSize: number): number {
    if (chunkSize < 1) {
        throw new Error(`Invalid chunk size ${chunkSize}`);
    }

    // An empty file still needs one (empty) part
    return Math.max(1, Math.ceil(size / chunkSize));
}

/**
 * First part number to ask the server for when resuming: the one following
 * the contiguous run of uploaded parts starting at 1. Parts may complete out
 * of order (parallel PUTs), so a gap in the middle is possible.
 */
export function getResumeFrom(uploadedParts: UploadPart[]): number {
    const uploaded = new Set(uploadedParts.map(p => p.PartNumber));
    let from = 1;
    while (uploaded.has(from)) {
        from++;
    }

    return from;
}

export function getPendingPartNumbers(
    partCount: number,
    uploadedParts: UploadPart[]
): number[] {
    const uploaded = new Set(uploadedParts.map(p => p.PartNumber));
    const pending: number[] = [];
    for (let n = 1; n <= partCount; n++) {
        if (!uploaded.has(n)) {
            pending.push(n);
        }
    }

    return pending;
}

export async function multipartUpload(
    transport: MultipartUploadTransport,
    file: File,
    {
        type = file.type,
        uploadParts: initialUploadParts,
        uploadId: initialUploadId,
        concurrency = DEFAULT_UPLOAD_CONCURRENCY,
        signal: externalSignal,
        onUploadInit,
        onPartUploaded,
        onProgress,
        receiveAbortController,
    }: MultipartUploadOptions = {}
): Promise<MultipartUpload> {
    const parts: UploadPart[] = [...(initialUploadParts ?? [])];
    const size = file.size;

    const createAbortError = () =>
        transport.createAbortError?.() ??
        new DOMException('Upload aborted', 'AbortError');

    // One controller for every request: a failed part aborts the others.
    const abortController = new AbortController();
    receiveAbortController?.(abortController);
    const signal = abortController.signal;
    if (externalSignal) {
        if (externalSignal.aborted) {
            abortController.abort();
        }
        externalSignal.addEventListener('abort', () => abortController.abort());
    }
    const throwIfAborted = () => {
        if (signal.aborted) {
            throw createAbortError();
        }
    };

    let uploadId: string;
    let chunkSize: number;
    let partCount: number;
    const urls: Record<string, string> = {};

    throwIfAborted();
    if (initialUploadId) {
        if (parts.length === 0) {
            throw new Error('uploadParts are required when resuming an upload');
        }
        uploadId = initialUploadId;
        const plan = await transport.getPartUrls(
            uploadId,
            getResumeFrom(parts),
            signal
        );
        chunkSize = plan.chunkSize;
        partCount = plan.partCount;
        Object.assign(urls, plan.urls);
    } else {
        if (parts.length > 0) {
            throw new Error(
                'uploadId is required when uploadParts are provided'
            );
        }
        if (!type) {
            throw new Error(
                `Unable to determine MIME type for file: ${file.name}`
            );
        }

        const init = await transport.createUpload(
            {filename: file.name, type, size},
            signal
        );
        uploadId = init.id;
        chunkSize = init.chunkSize;
        partCount = getPartCount(size, chunkSize);
        Object.assign(urls, init.urls);
        onUploadInit?.({uploadId});
    }

    // eslint-disable-next-line no-console
    console.debug(
        `Upload ${uploadId}: ${partCount} part(s) of ${chunkSize} bytes, ${parts.length} already uploaded`
    );

    const pending = getPendingPartNumbers(partCount, parts);
    const inFlight = new Set<number>();
    let refreshing: Promise<void> | undefined;

    /**
     * Presigned URLs expire during long transfers. Refreshing from the
     * smallest part not yet completed renews every remaining URL at once, so
     * concurrent workers share a single request.
     */
    const refreshUrls = (): Promise<void> => {
        if (!refreshing) {
            const from = Math.min(...inFlight, ...pending);
            refreshing = transport
                .getPartUrls(uploadId, from, signal)
                .then(plan => {
                    Object.assign(urls, plan.urls);
                })
                .finally(() => {
                    refreshing = undefined;
                });
        }

        return refreshing;
    };

    const getUrl = async (partNumber: number): Promise<string> => {
        if (!urls[partNumber]) {
            await refreshUrls();
        }
        const url = urls[partNumber];
        if (!url) {
            throw new Error(`No upload URL for part ${partNumber}`);
        }

        return url;
    };

    const partLength = (partNumber: number): number =>
        Math.max(0, Math.min(chunkSize, size - (partNumber - 1) * chunkSize));

    const loadedByPart = new Map<number, number>();
    let completedBytes = parts.reduce(
        (sum, p) => sum + partLength(p.PartNumber),
        0
    );

    const emitProgress = () => {
        let loaded = completedBytes;
        loadedByPart.forEach(v => {
            loaded += v;
        });
        loaded = Math.min(loaded, size);
        onProgress?.({
            loaded,
            total: size,
            progress: size > 0 ? loaded / size : 1,
        });
    };

    const putPart = (partNumber: number, url: string): Promise<string> => {
        const start = (partNumber - 1) * chunkSize;
        const blob =
            partNumber < partCount
                ? file.slice(start, start + chunkSize)
                : file.slice(start);

        return transport.putPart(url, blob, {
            signal,
            onProgress: loaded => {
                loadedByPart.set(partNumber, loaded);
                emitProgress();
            },
        });
    };

    const uploadPart = async (partNumber: number): Promise<void> => {
        inFlight.add(partNumber);
        try {
            let etag: string;
            try {
                etag = await putPart(partNumber, await getUrl(partNumber));
            } catch (e) {
                if (!transport.isExpiredUrlError(e) || signal.aborted) {
                    throw e;
                }
                delete urls[partNumber];
                etag = await putPart(partNumber, await getUrl(partNumber));
            }

            loadedByPart.delete(partNumber);
            completedBytes += partLength(partNumber);
            parts.push({ETag: etag, PartNumber: partNumber});
            onPartUploaded?.({uploadId, etag, partNumber});
            emitProgress();
        } finally {
            inFlight.delete(partNumber);
        }
    };

    const worker = async (): Promise<void> => {
        while (pending.length > 0) {
            throwIfAborted();
            await uploadPart(pending.shift()!);
        }
    };

    try {
        await Promise.all(
            Array.from(
                {length: Math.max(1, Math.min(concurrency, pending.length))},
                () => worker()
            )
        );
    } catch (e) {
        // Stop the other workers: a failed part fails the whole upload.
        abortController.abort();
        throw e;
    }

    parts.sort((a, b) => a.PartNumber - b.PartNumber);

    return {
        uploadId,
        parts,
    };
}
