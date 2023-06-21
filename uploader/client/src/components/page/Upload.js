import React, {Component} from 'react';
import '../../scss/Upload.scss';
import Dropzone from "react-dropzone";
import UploadForm from "./UploadForm";
import UploadProgress from "./UploadProgress";
import AssetUpload from "../AssetUpload";
import {Button} from "react-bootstrap";
import UploadDone from "./UploadDone";
import Container from "../Container";
import {Link, withRouter} from "react-router-dom";
import filesize from 'filesize';
import config from '../../config';
import {getTarget} from "../../requests";
import UploadBatch from "../../uploadBatch";
import {retrieveImageFromClipboardAsBlob} from "../ImagePaste";
import FullPageLoader from "../FullPageLoader";

const SELECT_FILES = 0;
const FILL_FORM = 1;
const UPLOAD = 2;
const UPLOAD_DONE = 3;

const {maxFileSize, maxCommitSize, maxFileCount} = config.all();

const quitMsg = `Are you sure you want to cancel the current upload?`;
function onBeforeUnload(e) {
        e = e || window.event;

        // For IE and Firefox
        if (e) {
            e.returnValue = quitMsg;
        }

        // For Safari
        return quitMsg;
}

function preventQuit() {
    window.onbeforeunload = onBeforeUnload;
}

function stopPreventQuit() {
    window.onbeforeunload = null;
}

class Upload extends Component {
    uploadBatch;
    state = {
        step: SELECT_FILES,
        files: [],
        errors: [],
        target: undefined,
        error: undefined,
    };

    getTargetId() {
        return this.props.match.params.id;
    }

    componentDidMount() {
        this.uploadBatch = new UploadBatch(this.getTargetId());
        this.uploadBatch.addErrorListener(this.onError);
        this.uploadBatch.addResumeListener(this.onResumeUpload);
        this.loadTarget();
        window.addEventListener('paste', this.onPaste);
    }

    componentWillUnmount() {
        this.uploadBatch.removeErrorListener(this.onError);
        this.uploadBatch.removeResumeListener(this.onResumeUpload);
        window.removeEventListener('paste', this.onPaste);
    }

    onPaste = (e) => {
        retrieveImageFromClipboardAsBlob(e, (imageBlob) => {
            this.onDrop([imageBlob]);
        });
    };

    async loadTarget() {
        try {
            const target = await getTarget(this.getTargetId());
            this.setState({target});
        } catch (e) {
            if (403 === e.res.statusCode) {
                this.setState({
                    error: `Unauthorized`
                });
            } else if (404 === e.res.statusCode)  {
                this.props.history.push('/', {replace: true});
            } else{
                throw e;
            }
        }
    }

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
        </div>
    }

    reset = () => {
        stopPreventQuit();
        this.uploadBatch.reset();
        this.setState({
            step: SELECT_FILES,
            files: [],
            errors: [],
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
        this.uploadBatch.addFiles(this.state.files);
        preventQuit();
        this.uploadBatch.startUpload();
        this.onNext();
    };

    onNext = () => {
        this.setState((prevState) => {
            return {
                step: prevState.step + 1
            }
        });
    };

    onFormData = (formData) => {
        this.uploadBatch.formData = formData;

        this.setState((state) => {
            return {
                step: state.step + 1,
            }
        });
    };

    render() {
        const {target, error} = this.state;

        if (error) {
            return <Container>
                <div>
                    {error}
                </div>
            </Container>
        }
        if (!target) {
            return <FullPageLoader/>
        }

        return <Container>
            <h2 style={{
                textAlign: 'center',
                fontSize: 20,
                marginBottom: 20,
            }}>{target.name}</h2>
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
                    targetId={this.getTargetId()}
                    onNext={this.onFormData}
                    onCancel={this.onCancel}
                />;
            case UPLOAD:
                return <UploadProgress
                    uploadBatch={this.uploadBatch}
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

                const allowedTypes = config.get('allowedTypes');
                const accept = Object.entries(accept)
                    .reduce((a, [mimeType, ext]) => [...a, mimeType, ...ext], [])
                    // Silently discard invalid entries as pickerOptionsFromAccept warns about these
                    .filter((v) => isMIMEType(v) || isExt(v))
                    .join(',');

                return <div className="upload-container">
                    <Dropzone
                        onDrop={this.onDrop}
                        multiple={maxFileCount !== 1}
                        accept={accept.length > 0 ? accept : undefined}
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
                        <Link to={`/download/${this.getTargetId()}`}>download</Link> URLs.
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

export default withRouter(Upload);

export function isMIMEType(v) {
    return (
        v === "audio/*" ||
        v === "video/*" ||
        v === "image/*" ||
        v === "text/*" ||
        /\w+\/[-+.\w]+/g.test(v)
    );
}
