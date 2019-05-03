import React, {Component} from 'react';
import '../../scss/Upload.scss';
import Dropzone from "react-dropzone";
import Download from "../Download";
import uploadBatch from "../../store/upload";
import UploadForm from "./UploadForm";
import UploadProgress from "./UploadProgress";
import AssetUpload from "../AssetUpload";
import {Button} from "react-bootstrap";
import UploadDone from "./UploadDone";

const SELECT_FILES = 0;
const FILL_FORM = 1;
const UPLOAD = 2;
const UPLOAD_DONE = 3;

export default class Upload extends Component {
    constructor(props) {
        super(props);

        this.state = {
            step: SELECT_FILES,
            files: [],
        };
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
                    key={index}
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

    onDrop = (acceptedFiles) => {
        const currentFiles = [...this.state.files, ...acceptedFiles];
        this.setState({files: currentFiles});
    };

    submit = () => {
        uploadBatch.addFiles(this.state.files);
        this.onNext();
    };

    onNext = () => {
        this.setState((state) => {
            return {
                step: state.step + 1
            }
        });
    };

    render() {
        const {step, files} = this.state;

        switch (step) {
            case FILL_FORM:
                return <UploadForm
                    files={files}
                    onNext={this.onNext}
                />;
            case UPLOAD:
                return <UploadProgress
                    files={files}
                    onNext={this.onNext}
                />;
            case UPLOAD_DONE:
                return <UploadDone goHome={this.reset} />;
            case SELECT_FILES:
            default:
                return (
                    <div className="container">
                        <div className="App">
                            <header>
                                <h1>Uploader.</h1>
                            </header>
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

                                <Button
                                    onClick={this.submit}
                                    disabled={this.state.files.length === 0}
                                >
                                    Next
                                </Button>
                            </div>
                            <div>
                                <p>or just paste an URL to grab:</p>
                                <Download/>
                            </div>
                        </div>
                    </div>
                );
        }
    }
}
