import type {UploadPart} from '@alchemy/api';

/**
 * What an interrupted upload resumes from: the multipart upload created on
 * the storage and its parts already sent. Kept in localStorage per user, so
 * that dropping the same file again — after a reload, a crash, another tab —
 * resumes from the first missing part instead of starting over.
 */
export type ResumeState = {
    uploadId: string;
    parts: UploadPart[];
    /** Epoch ms of the last change, to forget stale entries */
    updatedAt: number;
};

const STORAGE_PREFIX = 'upl.uploads.';

/**
 * The storage forgets the multipart uploads it was never told to complete
 * (lifecycle rules): an entry older than this is not worth resuming.
 */
export const RESUME_TTL_MS = 7 * 24 * 3600 * 1000;

/**
 * Identifies a file across page loads: browsers give no stable id, but the
 * same file dropped again has the same path, size and modification date.
 */
export function getFileKey(file: File): string {
    const path =
        (file as File & {webkitRelativePath?: string}).webkitRelativePath ||
        file.name;

    return `${file.size}:${file.lastModified}:${path}`;
}

type Entries = Record<string, ResumeState>;

export class ResumeStorage {
    constructor(
        private readonly userKey: string,
        private readonly storage: Pick<
            Storage,
            'getItem' | 'setItem' | 'removeItem'
        > | null = getLocalStorage(),
        private readonly now: () => number = () => Date.now()
    ) {}

    private get key(): string {
        return `${STORAGE_PREFIX}${this.userKey}`;
    }

    private read(): Entries {
        if (!this.storage) {
            return {};
        }
        try {
            const data: unknown = JSON.parse(
                this.storage.getItem(this.key) ?? '{}'
            );
            if (!data || typeof data !== 'object' || Array.isArray(data)) {
                return {};
            }
            const limit = this.now() - RESUME_TTL_MS;

            return Object.fromEntries(
                Object.entries(data as Entries).filter(
                    ([, e]) =>
                        e &&
                        typeof e.uploadId === 'string' &&
                        Array.isArray(e.parts) &&
                        e.updatedAt > limit
                )
            );
        } catch {
            return {};
        }
    }

    private write(entries: Entries): void {
        if (!this.storage) {
            return;
        }
        try {
            if (Object.keys(entries).length === 0) {
                this.storage.removeItem(this.key);
            } else {
                this.storage.setItem(this.key, JSON.stringify(entries));
            }
        } catch {
            // Quota exceeded or storage disabled: resuming is a bonus
        }
    }

    get(fileKey: string): ResumeState | undefined {
        const entry = this.read()[fileKey];

        return entry && entry.parts.length > 0 ? entry : undefined;
    }

    start(fileKey: string, uploadId: string): void {
        const entries = this.read();
        entries[fileKey] = {uploadId, parts: [], updatedAt: this.now()};
        this.write(entries);
    }

    addPart(fileKey: string, uploadId: string, part: UploadPart): void {
        const entries = this.read();
        const entry = entries[fileKey];
        if (!entry || entry.uploadId !== uploadId) {
            return;
        }
        entry.parts = [
            ...entry.parts.filter(p => p.PartNumber !== part.PartNumber),
            part,
        ];
        entry.updatedAt = this.now();
        this.write(entries);
    }

    clear(fileKey: string): void {
        const entries = this.read();
        if (fileKey in entries) {
            delete entries[fileKey];
            this.write(entries);
        }
    }
}

function getLocalStorage(): Storage | null {
    try {
        return typeof window !== 'undefined' ? window.localStorage : null;
    } catch {
        return null;
    }
}
