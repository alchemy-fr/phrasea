const uploadStorage = window.localStorage;
const key = 'uploadState';

class UploadStateStorage {
    cache;
    writeTimeout;

    getUpload(userId, fileUID) {
        const d = this.getData();

        if (!d[userId]) {
            return;
        }

        return d[userId][fileUID];
    }

    initUpload(userId, fileUID, uploadId) {
        const d = this.getData();

        d[userId] = d[userId] || {};
        d[userId][fileUID] = {
            u: uploadId,
            c: [],
        };

        this.setData(d);
    }

    updateUpload(userId, fileUID, chunkETag, partNumber) {
        console.debug('updateUpload', userId, fileUID, chunkETag, partNumber);
        const d = this.getData();
        const list = d[userId][fileUID].c;
        if (!list.find(({n}) => n === partNumber)) {
            list.push({etag: chunkETag, n: partNumber});
        }
        this.setData(d);
    }

    removeUpload(userId, fileUID) {
        const d = this.getData();
        delete d[userId][fileUID];
        this.setData(d);
    }

    getData() {
        if (this.cache) {
            return this.cache;
        }

        const item = uploadStorage.getItem(key);
        return (this.cache = item ? JSON.parse(item) : {});
    }

    setData(data) {
        this.cache = data;

        if (this.writeTimeout) {
            clearTimeout(this.writeTimeout);
        }

        this.writeTimeout = setTimeout(() => {
            uploadStorage.setItem(key, JSON.stringify(data));
        }, 100);
    }
}

export function getUniqueFileId(file, fileChunkSize) {
    const relativePath =
        file.webkitRelativePath ||
        file.relativePath ||
        file.fileName ||
        file.name;

    return `${file.size}-${fileChunkSize}-${relativePath}`;
}

export const uploadStateStorage = new UploadStateStorage();
