import React, {Component} from 'react';
import '../scss/Upload.scss';
import Dropzone from "react-dropzone";
import AssetUpload from "./AssetUpload";

export default class Upload extends Component {
    batchSize = 2;

    constructor(props) {
        super(props);

        this.state = {
            files: [],
            uploading: false,
            currentFileUpload: null,
            totalSize: null,
            totalUploaded: null,
        };

        this.fileRefs = {};
        this.currentUpload = 0;

        this.onDrop = this.onDrop.bind(this);
        this.startUpload = this.startUpload.bind(this);
        this.onUploadComplete = this.onUploadComplete.bind(this);
        this.onUploadProgress = this.onUploadProgress.bind(this);
    }

    startUpload() {
        this.setState({
            totalUploaded: 0,
            uploading: true,
        }, () => {
            const batchSize = this.batchSize > this.state.files.length ? this.state.files.length : this.batchSize
            for (let i = 0; i < batchSize; i++) {
                this.fileRefs[this.currentUpload].upload();
                if ((i+1) < batchSize) {
                    ++this.currentUpload;
                }
            }
        });
    }

    onUploadComplete() {
        ++this.currentUpload;
        if (this.currentUpload >= this.state.files.length) {
            return;
        }
        this.fileRefs[this.currentUpload].upload();
    }

    onUploadProgress() {
        let totalUploaded = 0;
        Object.keys(this.fileRefs).forEach((i) => {
            const fileComp = this.fileRefs[i];
            totalUploaded += fileComp.getBytesLoaded();
        });

        this.setState({
            totalUploaded,
        });
    }

    onDrop(acceptedFiles) {
        const currentFiles = [...this.state.files, ...acceptedFiles];
        console.log('currentFiles', currentFiles);

        this.setState({
            files: currentFiles,
            totalSize: currentFiles.reduce((total, file) => total + file.size, 0)
        });
    }

    renderFiles() {
        const {files} = this.state;

        return <div className="file-collection">
            {files.map((file, index) => {
                return <AssetUpload
                    ref={(ref) => this.fileRefs[index] = ref}
                    key={index}
                    file={file}
                    onUploadComplete={this.onUploadComplete}
                    onUploadProgress={this.onUploadProgress}
                />
            })}
        </div>;
    }

    renderProgressBar() {
        const {
            totalUploaded,
            totalSize
        } = this.state;

        const progress = totalUploaded / totalSize * 100;

        return <div className="progress">
            <div className="progress-bar"
                 role="progressbar"
                 style={{width: progress+'%'}}
                 aria-valuenow={progress}
                 aria-valuemin="0"
                 aria-valuemax="100"
            />
        </div>;
    }

    render() {
        const {
            files,
            uploading,
        } = this.state;

        return (
            <div>
                <Dropzone
                    onDrop={this.onDrop}
                >
                    {({getRootProps, getInputProps, isDragActive}) => {
                        let classes = ['Upload'];
                        if (isDragActive) {
                            classes.push('drag-over');
                        }
                        return (
                            <div {...getRootProps()} className={classes.join(' ')}>
                                <input {...getInputProps()} />
                                {files.length > 0 ?
                                    this.renderFiles()
                                    : <p>Drag 'n' drop some files here, or click to select files</p>
                                }
                            </div>
                        )
                    }}
                </Dropzone>

                {uploading ? this.renderProgressBar() : ''}

                <button
                    onClick={this.startUpload}
                    disabled={uploading}
                >
                    Next
                </button>
            </div>
        );
    }
}
