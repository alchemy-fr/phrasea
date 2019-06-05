import auth from "./auth";
import request from "superagent";
import config from "./config";

class UploadBatch
{
    files;
    uploading;
    batchSize = 2;
    currentUpload;
    totalSize;
    progresses;
    formData;
    progressListeners;
    fileCompleteListeners;
    completeListeners;
    completeEvent;

    constructor() {
        this.reset();
    }

    reset() {
        this.files = [];
        this.formData = null;
        this.uploading = false;
        this.currentUpload = null;
        this.totalSize = 0;
        this.progresses = {};
        this.completeEvent = null;
        this.resetListeners();
    }

    resetListeners() {
        this.progressListeners = [];
        this.fileCompleteListeners = [];
        this.completeListeners = [];
    }

    addFiles(files) {
        this.files = this.files.concat(files);
        this.totalSize += files.reduce((total, file) => total + file.size, 0)
    }

    registerProgressHandler(callback) {
        this.progressListeners.push(callback);
    }

    registerFileCompletehandler(callback) {
        this.fileCompleteListeners.push(callback);

        // Trigger for already uploaded files
        this.files.forEach((file) => {
            if (file.event) {
                callback(file.event);
            }
        });
    }

    registerCompleteHandler(callback) {
        this.completeListeners.push(callback);

        // Trigger if all files have been uploaded
        if (this.completeEvent) {
            callback(this.completeEvent);
        }
    }

    startUpload() {
        this.uploading = true;
        this.currentUpload = 0;

        const batchSize = this.batchSize > this.files.length ? this.files.length : this.batchSize;
        for (let i = 0; i < batchSize; i++) {
            this.uploadFile(this.currentUpload, this.files[this.currentUpload]);
            if ((i + 1) < batchSize) {
                ++this.currentUpload;
            }
        }
    }

    commit() {
        const idCollection = this.files.map(file => file.id);

        const formData = {
            files: idCollection,
            formData: this.formData,
        };
        const accessToken = auth.getAccessToken();

        request
            .post(config.getUploadBaseURL() + '/commit')
            .accept('json')
            .set('Authorization', `Bearer ${accessToken}`)
            .send(formData)
            .end((err, res) => {
                auth.isResponseValid(err, res);
            });
    }

    uploadFile(index, file) {
        const formData = new FormData();
        formData.append('file', file);

        const accessToken = auth.getAccessToken();

        request
            .post(config.getUploadBaseURL() + '/assets')
            .accept('json')
            .set('Authorization', `Bearer ${accessToken}`)
            .on('progress', (e) => {
                if (e.direction !== 'upload') {
                    return;
                }
                this.onUploadProgress(e, index);
            })
            .send(formData)
            .end((err, res) => {
                if (!auth.isResponseValid(err, res)) {
                    return;
                }

                this.onFileComplete(err, res, index);
            });
    }

    onFileComplete(err, res, index) {
        ++this.currentUpload;

        const data = JSON.parse(res.text);
        this.files[index].id = data.id;

        if (this.currentUpload >= this.files.length) {
            this.onComplete();
            return;
        }

        let totalLoaded = 0;
        Object.keys(this.progresses).forEach((i) => {
            totalLoaded += this.progresses[i];
        });

        const e = {
            totalLoaded,
            totalSize: this.totalSize,
            totalPercent: Math.round(totalLoaded / this.totalSize * 100),
            fileSize: this.files[index].size,
            fileLoaded: this.files[index].size,
            filePercent: 100,
            index,
            err,
            res
        };

        // Register event for future handlers
        this.files[index].event = e;

        this.fileCompleteListeners.forEach((func) => {
            func(e);
        });

        this.uploadFile(this.currentUpload, this.files[this.currentUpload]);
    }

    onComplete() {
        this.progressListeners = [];
        this.fileCompleteListeners = [];

        const e = {
            totalLoaded: this.totalSize,
            totalSize: this.totalSize,
            totalPercent: 100,
        };

        this.completeEvent = e;

        this.completeListeners.forEach((func) => {
            func(e);
        });
        this.completeListeners = [];
    }

    onUploadProgress(event, index) {
        this.progresses[index] = event.loaded;

        let totalLoaded = 0;
        Object.keys(this.progresses).forEach((i) => {
            totalLoaded += this.progresses[i];
        });

        const e = {
            totalLoaded,
            totalSize: this.totalSize,
            totalPercent: Math.round(totalLoaded / this.totalSize * 100),
            fileSize: this.files[index].size,
            fileLoaded: event.loaded,
            filePercent: Math.round(event.loaded / this.files[index].size * 100),
            index,
        };

        this.progressListeners.forEach((func) => {
            func(e);
        });
    }
}

const uploadBatch = new UploadBatch();

export default uploadBatch;
