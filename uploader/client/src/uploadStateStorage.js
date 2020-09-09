const uploadStorage = window.localStorage;
const key = 'uploadState';

class UploadStateStorage {
    getUpload(userId, fileUID) {
        const d = this.getData();

        if (!d[userId]) {
            return;
        }

        return d[userId][fileUID];
    }

    initUpload(userId, fileUID, uploadId, path) {
        const d = this.getData();

        d[userId] = d[userId] || {};
        d[userId][fileUID] = {
            u: uploadId,
            p: path,
            c: [],
        };

        this.setData(d);
    }

    updateUpload(userId, fileUID, chunkETag) {
        const d = this.getData();
        d[userId][fileUID].c.push(chunkETag);

        this.setData(d);
    }

    removeUpload(userId, fileUID) {
        const d = this.getData();
        delete d[userId][fileUID];
        this.setData(d);
    }

    getData() {
        const item = uploadStorage.getItem(key);
        return item ? JSON.parse(item) : {};
    }

    setData(data) {
        return uploadStorage.setItem(key, JSON.stringify(data));
    }
}

export function getUniqueFileId(file) {
    const relativePath = file.webkitRelativePath || file.relativePath || file.fileName || file.name;

    return `${file.size}-${relativePath}`;
}

export const uploadStateStorage = new UploadStateStorage();
