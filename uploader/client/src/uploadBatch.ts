import axios, {AxiosProgressEvent} from 'axios';
import {apiClient, oauthClient} from './init.ts';
import {
    AbortableFile,
    FileCompleteEvent,
    FileUploadError,
    OnFileCompleteListener,
    UploadedAsset,
    UploadFormData,
} from './types.ts';
import {uploadMultipartFile} from './multiPartUpload.ts';

type OnProgressListener = (e: {
    totalLoaded: number;
    totalSize: number;
    totalPercent: number;
    fileSize: number;
    fileLoaded: number;
    filePercent: number;
    index: number;
}) => void;

type OnErrorListener = (error: string) => void;
type OnResumeListener = () => void;
type OnCompleteListener = (event: CompleteEvent) => void;
type CompleteEvent = {
    totalLoaded: number;
    totalSize: number;
    totalPercent: number;
};

export default class UploadBatch {
    private files: AbortableFile[] = [];
    private uploading: boolean = false;
    private batchSize: number = 2;
    private currentUpload: number = 0;
    private totalSize: number = 0;
    private progresses: Record<string, number> = {};
    public formData?: UploadFormData;
    private targetId: string;
    public schemaId?: string;
    private progressListeners: OnProgressListener[] = [];
    private errorListeners: OnErrorListener[] = [];
    private resumeListeners: OnResumeListener[] = [];
    private fileCompleteListeners: OnFileCompleteListener[] = [];
    private completeListeners: OnCompleteListener[] = [];
    private completeEvent?: CompleteEvent;
    private failedUploads: (() => Promise<void>)[] = [];

    constructor(targetId: string) {
        this.targetId = targetId;
        this.reset();
    }

    reset() {
        this.files.forEach(file => {
            if (file.abortController) {
                file.abortController.abort();
            }
        });
        this.files = [];
        this.formData = undefined;
        this.uploading = false;
        this.currentUpload = 0;
        this.totalSize = 0;
        this.progresses = {};
        this.completeEvent = undefined;
        this.failedUploads = [];
        this.resetListeners();

        window.addEventListener('online', this.retryUploads);
    }

    public getFileProgress(file: File): number {
        const index = this.files.findIndex(f => f.file === file);
        if (index >= 0 && this.progresses[index] !== undefined) {
            const loaded = this.progresses[index];
            return (loaded / file.size) * 100;
        }

        return 0;
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
        this.resumeListeners.forEach(func => {
            func();
        });

        const uploads = [...this.failedUploads];
        this.failedUploads = [];
        for (let i = 0; i < uploads.length; i++) {
            uploads[i]();
        }
    };

    addFiles(files: File[]) {
        this.files = this.files.concat(
            files.map(file => {
                return {
                    file,
                    abortController: null,
                };
            })
        );
        this.totalSize += files.reduce((total, file) => total + file.size, 0);
    }

    registerProgressHandler(callback: OnProgressListener) {
        this.progressListeners.push(callback);
    }

    registerFileCompleteHandler(callback: OnFileCompleteListener) {
        this.fileCompleteListeners.push(callback);

        // Trigger for already uploaded files
        this.files.forEach(file => {
            if (file.event) {
                callback(file.event);
            }
        });
    }

    registerCompleteHandler(callback: OnCompleteListener) {
        this.completeListeners.push(callback);

        // Trigger if all files have already been uploaded
        if (this.completeEvent) {
            callback(this.completeEvent);
        }
    }

    startUpload() {
        this.uploading = true;
        this.currentUpload = 0;

        const batchSize =
            this.batchSize > this.files.length
                ? this.files.length
                : this.batchSize;
        for (let i = 0; i < batchSize; i++) {
            this.uploadFile(this.currentUpload);
            if (i + 1 < batchSize) {
                ++this.currentUpload;
            }
        }
    }

    async commit() {
        const idCollection = this.files.map(file => {
            return file.id;
        });

        const formData = {
            files: idCollection,
            formData: this.formData,
            schemaId: this.schemaId,
            target: `/targets/${this.targetId}`,
        };

        this.reset();

        await apiClient.post('/commit', formData);
    }

    async uploadFile(index: number, retry = 0): Promise<void> {
        const file = this.files[index];

        const username = oauthClient.getDecodedToken()!.preferred_username;

        try {
            const res = await uploadMultipartFile(
                this.targetId,
                username,
                file,
                e => {
                    this.onUploadProgress(e, index);
                }
            );
            this.onFileComplete(res, index);
        } catch (err: any) {
            if (navigator && !navigator.onLine) {
                return new Promise((resolve, reject) => {
                    const retryCallback = async () => {
                        try {
                            const res = await this.uploadFile(index, retry);
                            resolve(res);
                        } catch (e) {
                            reject(e);
                        }
                    };
                    this.onFileError(
                        `Your connection seems offline. Upload will resume automatically when back online!`,
                        index
                    );
                    this.failedUploads.push(retryCallback);
                });
            } else if (retry < 10) {
                if (!axios.isCancel(err)) {
                    return await this.uploadFile(index, retry + 1);
                } else {
                    return;
                }
            }

            this.onFileError(err.toString(), index);
        }
    }

    onFileError(err: FileUploadError, index: number) {
        if (!this.files[index]) {
            return;
        }
        this.files[index].error = err;

        this.errorListeners.forEach(func => {
            func(err);
        });
    }

    onFileComplete(asset: UploadedAsset, index: number) {
        this.files[index].id = asset.id;

        let totalLoaded = 0;
        Object.keys(this.progresses).forEach(i => {
            totalLoaded += this.progresses[i];
        });

        const fileSize = this.files[index].file.size;
        const e: FileCompleteEvent = {
            totalLoaded,
            totalSize: this.totalSize,
            totalPercent: Math.round((totalLoaded / this.totalSize) * 100),
            fileSize,
            fileLoaded: fileSize,
            filePercent: 100,
            index,
            asset,
        };

        // Register event for future handlers
        this.files[index].event = e;

        this.fileCompleteListeners.forEach(func => {
            func(e);
        });

        ++this.currentUpload;
        if (this.currentUpload < this.files.length) {
            this.uploadFile(this.currentUpload);
        } else if (this.everyFilesCompleted()) {
            this.onComplete();
        }
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

        const e: CompleteEvent = {
            totalLoaded: this.totalSize,
            totalSize: this.totalSize,
            totalPercent: 100,
        };

        this.completeEvent = e;

        this.completeListeners.forEach(func => {
            func(e);
        });
        this.completeListeners = [];
    }

    onUploadProgress(event: AxiosProgressEvent, index: number) {
        this.progresses[index] = event.loaded;

        let totalLoaded = 0;
        Object.keys(this.progresses).forEach(i => {
            totalLoaded += this.progresses[i];
        });

        const fileSize = this.files[index].file.size;
        const e = {
            totalLoaded,
            totalSize: this.totalSize,
            totalPercent: Math.round((totalLoaded / this.totalSize) * 100),
            fileSize,
            fileLoaded: event.loaded,
            filePercent: Math.round((event.loaded / fileSize) * 100),
            index,
        };

        this.progressListeners.forEach(func => {
            func(e);
        });
    }

    addErrorListener(handler: OnErrorListener) {
        this.errorListeners.push(handler);
    }

    removeErrorListener(handler: OnErrorListener) {
        const index = this.errorListeners.findIndex(h => h === handler);
        if (index >= 0) {
            this.errorListeners.slice(index, 1);
        }
    }

    addResumeListener(handler: OnResumeListener) {
        this.resumeListeners.push(handler);
    }

    removeResumeListener(handler: OnResumeListener) {
        const index = this.resumeListeners.findIndex(h => h === handler);
        if (index >= 0) {
            this.resumeListeners.slice(index, 1);
        }
    }
}
