import request from "superagent";
import config from "./config";
import {oauthClient} from "./oauth";
import {uploadMultipartFile} from "./multiPartUpload";

export default class UploadBatch {
    files = [];
    uploading;
    batchSize = 2;
    currentUpload;
    totalSize;
    progresses;
    formData;
    targetId;
    progressListeners;
    errorListeners;
    resumeListeners;
    fileCompleteListeners;
    completeListeners;
    completeEvent;
    failedUploads;

    constructor(targetId) {
        this.targetId = targetId;
        this.reset();
    }

    reset() {
        this.abort();
        this.files = [];
        this.formData = null;
        this.uploading = false;
        this.currentUpload = null;
        this.totalSize = 0;
        this.progresses = {};
        this.completeEvent = null;
        this.failedUploads = [];
        this.resetListeners();

        window.addEventListener('online', this.retryUploads);
    }

    abort() {
        this.files.forEach((file) => {
            if (file.request) {
                file.request.abort();
            }
        });
    }

    resetListeners() {
        this.progressListeners = [];
        this.fileCompleteListeners = [];
        this.completeListeners = [];
        this.errorListeners = [];
        this.resumeListeners = [];

        window.removeEventListener('online', this.retryUploads);
    }

    retryUploads = () => {
        this.resumeListeners.forEach((func) => {
            func();
        });

        const uploads = [...this.failedUploads];
        this.failedUploads = [];
        for (let i = 0; i < uploads.length; i++) {
            uploads[i]();
        }
    }

    addFiles(files) {
        this.files = this.files.concat(files.map(file => {
            return {
                file,
                request: null
            };
        }));
        this.totalSize += files.reduce((total, file) => total + file.size, 0)
    }

    registerProgressHandler(callback) {
        this.progressListeners.push(callback);
    }

    registerFileCompleteHandler(callback) {
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

        // Trigger if all files have already been uploaded
        if (this.completeEvent) {
            callback(this.completeEvent);
        }
    }

    startUpload() {
        this.uploading = true;
        this.currentUpload = 0;

        const batchSize = this.batchSize > this.files.length ? this.files.length : this.batchSize;
        for (let i = 0; i < batchSize; i++) {
            this.uploadFile(this.currentUpload);
            if ((i + 1) < batchSize) {
                ++this.currentUpload;
            }
        }
    }

    commit() {
        const idCollection = this.files.map(file => {
            return file.id;
        });

        const formData = {
            files: idCollection,
            formData: this.formData,
            target: `/targets/${this.targetId}`,
        };
        const accessToken = oauthClient.getAccessToken();

        request
            .post(config.getUploadBaseURL() + '/commit')
            .accept('json')
            .set('Authorization', `Bearer ${accessToken}`)
            .send(formData)
            .end((err, res) => {
                if (!oauthClient.isResponseValid(err, res)) {
                    alert('Failed to commit assets');
                    console.log(res);
                    throw err;
                }
            });
    }

    async uploadFile(index, retry = 0) {
        const file = this.files[index];

        const accessToken = oauthClient.getAccessToken();
        const username = oauthClient.getUsername();

        try {
            const res = await uploadMultipartFile(this.targetId, username, accessToken, file, (e) => {
                this.onUploadProgress(e, index);
            });
            this.onFileComplete(res, index);
        } catch (err) {
            if (navigator && !navigator.onLine) {
                return new Promise((resolve, reject) => {
                    const retryCallback = async () => {
                        try {
                            const res = await this.uploadFile(index, retry);
                            resolve(res);
                        } catch (e) {
                            reject(e);
                        }
                    }
                    this.onFileError(`Your connection seems offline. Upload will resume automatically when back online!`, index);
                    this.failedUploads.push(retryCallback);
                });
            } else if (retry < 10) {
                return await this.uploadFile(index, retry + 1);
            }

            this.onFileError(err, index);
        }
    }

    onFileError(err, index) {
        this.files[index].error = err;

        this.errorListeners.forEach((func) => {
            func(err);
        });
    }

    onFileComplete(res, index) {
        const data = JSON.parse(res.text);
        this.files[index].id = data.id;

        let totalLoaded = 0;
        Object.keys(this.progresses).forEach((i) => {
            totalLoaded += this.progresses[i];
        });

        const fileSize = this.files[index].file.size;
        const e = {
            totalLoaded,
            totalSize: this.totalSize,
            totalPercent: Math.round(totalLoaded / this.totalSize * 100),
            fileSize,
            fileLoaded: fileSize,
            filePercent: 100,
            index,
            res
        };

        // Register event for future handlers
        this.files[index].event = e;

        this.fileCompleteListeners.forEach((func) => {
            func(e);
        });

        ++this.currentUpload;
        if (this.currentUpload < this.files.length) {
            this.uploadFile(this.currentUpload);
        } else if (this.everyFilesCompleted()) {
            this.onComplete();
        }

        delete this.files[index].request;
    }

    everyFilesCompleted() {
        for (let i = 0; i < this.files.length; i++) {
            if (!this.files[i].event) {
                return false;
            }
        }

        return true;
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

        const fileSize = this.files[index].file.size;
        const e = {
            totalLoaded,
            totalSize: this.totalSize,
            totalPercent: Math.round(totalLoaded / this.totalSize * 100),
            fileSize,
            fileLoaded: event.loaded,
            filePercent: Math.round(event.loaded / fileSize * 100),
            index,
        };

        this.progressListeners.forEach((func) => {
            func(e);
        });
    }

    addErrorListener(handler) {
        this.errorListeners.push(handler);
    }

    removeErrorListener(handler) {
        const index = this.errorListeners.findIndex(h => h === handler);
        if (index >= 0) {
            this.errorListeners.slice(index, 1);
        }
    }

    addResumeListener(handler) {
        this.resumeListeners.push(handler);
    }

    removeResumeListener(handler) {
        const index = this.resumeListeners.findIndex(h => h === handler);
        if (index >= 0) {
            this.resumeListeners.slice(index, 1);
        }
    }
}
