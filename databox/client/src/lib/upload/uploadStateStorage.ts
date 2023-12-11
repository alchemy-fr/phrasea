const uploadStorage = window.localStorage;
const key = 'uploadState';

type Chunk = string;

type Upload = {
    u: string;
    p: string;
    c: Chunk[];
};

type FileIndex = {
    [key: string]: Upload;
};

type UserIndex = {
    [key: string]: FileIndex;
};

class UploadStateStorage {
    cache: UserIndex | undefined;
    writeTimeout: ReturnType<typeof setTimeout> | undefined;

    getUpload(userId: string, fileUID: string) {
        const d = this.getData();

        if (!d[userId]) {
            return;
        }

        return d[userId][fileUID];
    }

    initUpload(
        userId: string,
        fileUID: string,
        uploadId: string,
        path: string
    ): void {
        const d = this.getData();

        d[userId] = d[userId] || {};
        d[userId][fileUID] = {
            u: uploadId,
            p: path,
            c: [],
        };

        this.setData(d);
    }

    updateUpload(userId: string, fileUID: string, chunkETag: string): void {
        const d = this.getData();
        d[userId][fileUID].c.push(chunkETag);
        this.setData(d);
    }

    removeUpload(userId: string, fileUID: string): void {
        const d = this.getData();
        if (d && !!d[userId]) {
            delete d[userId][fileUID];
        }
        this.setData(d);
    }

    getData(): UserIndex {
        if (this.cache) {
            return this.cache;
        }

        const item = uploadStorage.getItem(key);
        return (this.cache = item ? (JSON.parse(item) as Storage) : {});
    }

    setData(data: UserIndex): void {
        this.cache = data;

        if (this.writeTimeout) {
            clearTimeout(this.writeTimeout);
        }

        this.writeTimeout = setTimeout(() => {
            uploadStorage.setItem(key, JSON.stringify(data));
        }, 100);
    }
}

type BrowserFile = {
    webkitRelativePath?: string;
    relativePath?: string;
    fileName?: string;
} & File;

export function getUniqueFileId(
    file: BrowserFile,
    fileChunkSize: number
): string {
    const relativePath =
        file.webkitRelativePath ||
        file.relativePath ||
        file.fileName ||
        file.name;

    return `${file.size}-${fileChunkSize}-${relativePath}`;
}

export const uploadStateStorage = new UploadStateStorage();
