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

    constructor() {
        this.reset();
    }

    reset() {
        this.files = [];
        this.formData = {};
        this.uploading = false;
        this.currentUpload = null;
        this.totalSize = 0;
        this.progressListeners = [];
        this.fileCompleteListeners = [];
        this.completeListeners = [];
        this.progresses = {};
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
    }

    registerCompletehandler(callback) {
        this.completeListeners.push(callback);
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
                this.onFileComplete(err, res, index);
            });
    }

    onFileComplete(err, res, index) {
        ++this.currentUpload;
        if (this.currentUpload >= this.files.length) {
            this.onComplete();
            return;
        }

        this.fileCompleteListeners.forEach((func) => {
            func(err, res, index);
        });

        this.uploadFile(this.currentUpload, this.files[this.currentUpload]);
    }

    onComplete() {
        this.progressListeners = [];
        this.fileCompleteListeners = [];
        this.completeListeners.forEach((func) => {
            func();
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
