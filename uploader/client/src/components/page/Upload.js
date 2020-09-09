import React, {Component} from 'react';
import '../../scss/Upload.scss';
import Dropzone from "react-dropzone";
import uploadBatch from "../../upload";
import UploadForm from "./UploadForm";
import UploadProgress from "./UploadProgress";
import AssetUpload from "../AssetUpload";
import {Button} from "react-bootstrap";
import UploadDone from "./UploadDone";
import Container from "../Container";
import {Link} from "react-router-dom";
import filesize from 'filesize';
import config from '../../config';

const SELECT_FILES = 0;
const FILL_FORM = 1;
const UPLOAD = 2;
const UPLOAD_DONE = 3;

const {maxFileSize, maxCommitSize, maxFileCount} = config.all();

export default class Upload extends Component {
    state = {
        step: SELECT_FILES,
        files: [],
        errors: [],
    };

    onError = (err) => {
        this.setState(prevState => {
            const errors = [...prevState.errors];
            errors.push(`Upload issue: ${err.toString()}`);
            return {errors};
        })
    }

    onResumeUpload = () => {
        this.setState({errors: []});
    }

    componentDidMount() {
        uploadBatch.addErrorListener(this.onError);
        uploadBatch.addResumeListener(this.onResumeUpload);
    }

    componentWillUnmount() {
        uploadBatch.removeErrorListener(this.onError);
        uploadBatch.removeResumeListener(this.onResumeUpload);
    }

    removeFile = (index) => {
        this.setState((prevState) => {
            return {
                files: prevState.files.filter((file, i) => i !== index)
            };
        });
    };

    renderFiles() {
        const {files} = this.state;

        return <div className="file-collection">
            {files.map((file, index) => {
                return <AssetUpload
                    key={file.id}
                    onRemove={() => this.removeFile(index)}
                    file={file}
                />
            })}
        </div>;
    }

    reset = () => {
        uploadBatch.reset();
        this.setState({
            step: SELECT_FILES,
            files: [],
        });
    };

    onCancel = () => {
        if (window.confirm(`Are you sure you want to cancel current upload?`)) {
            this.reset();
        }
    };

    onDrop = (acceptedFiles) => {
        let newFiles = acceptedFiles.map(f => {
            f.id = '_' + Math.random().toString(36).substr(2, 9);

            if (maxFileSize && f.size > maxFileSize) {
                alert(`Size of ${f.name} is higher than ${filesize(maxFileSize)} (${filesize(f.size)})`);
                return null;
            }

            return f;
        }).filter(f => null !== f);

        const currentFiles = maxFileCount === 1 ? newFiles : [...this.state.files, ...newFiles];

        this.setState({files: currentFiles});
    };

    submit = () => {
        if (!this.canSubmit()) {
            return;
        }
        uploadBatch.addFiles(this.state.files);
        uploadBatch.startUpload();
        this.onNext();
    };

    onNext = () => {
        this.setState((state) => {
            return {
                step: state.step + 1
            }
        });
    };

    onFormData = (formData) => {
        uploadBatch.formData = formData;

        this.setState((state) => {
            return {
                step: state.step + 1,
            }
        });
    };

    render() {
        return <Container>
            {this.renderUploadErrors()}
            {this.renderContent()}
        </Container>
    }

    renderContent() {
        const {step, files} = this.state;

        switch (step) {
            case FILL_FORM:
                return <UploadForm
                    files={files}
                    onNext={this.onFormData}
                    onCancel={this.onCancel}
                />;
            case UPLOAD:
                return <UploadProgress
                    files={files}
                    onNext={this.onNext}
                    onCancel={this.onCancel}
                />;
            case UPLOAD_DONE:
                return <UploadDone goHome={this.reset}/>;
            case SELECT_FILES:
            default:
                const errors = [];
                const canSubmit = this.canSubmit(errors);

                return <div className="upload-container">
                    <Dropzone
                        onDrop={this.onDrop}
                        multiple={maxFileCount !== 1}
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

                    <ul className="specs">
                        <li>
                            {`Files: ${this.state.files.length}`}
                            {maxFileCount ? ` / ${maxFileCount}` : ''}
                        </li>
                        <li>
                            {`Total size: ${filesize(this.getTotalSize())}`}
                            {maxCommitSize ? ` / ${filesize(maxCommitSize)}` : ''}
                        </li>
                        {maxFileSize ? <li>{`Max file size: ${filesize(maxFileSize)}`}</li> : ''}
                    </ul>

                    {this.renderErrors(errors)}
                    <Button
                        size="lg"
                        onClick={this.submit}
                        disabled={!canSubmit}
                    >
                        Next
                    </Button>

                    <hr/>
                    <p>
                        or just{' '}
                        <Link to="/download">download</Link> URLs.
                    </p>
                </div>
        }
    }

    renderUploadErrors() {
        const {errors} = this.state;
        if (errors.length === 0) {
            return '';
        }

        return <div className="alert alert-danger">
            {errors.map((e, i) => <div key={i}>
                {e}
            </div>)}
        </div>
    }

    renderErrors(errors) {
        if (errors.length === 0) {
            return '';
        }

        return <ul className="errors">
            {errors.map(e => <li
                key={e}
            >
                {e}
            </li>)}
        </ul>
    }

    getTotalSize = () => {
        const {files} = this.state;

        return files.length > 0
            ? files.reduce((total, f) => total + f.size, 0)
            : 0;
    };

    canSubmit = (errors) => {
        if (this.state.files.length === 0) {
            return false;
        }

        if (maxCommitSize) {
            const totalSize = this.getTotalSize();
            if (totalSize > maxCommitSize) {
                errors.push(`Total max file size exceeded (${filesize(totalSize)} > ${filesize(maxCommitSize)})`);

                return false;
            }
        }

        if (maxFileCount) {
            const fileCount = this.state.files.length;
            if (fileCount > maxFileCount) {
                errors.push(`Total max file count exceeded (${fileCount} > ${maxFileCount})`);

                return false;
            }
        }

        return true;
    }
}
