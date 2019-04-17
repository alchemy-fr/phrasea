import React, {Component} from 'react';
import '../scss/Upload.scss';
import Dropzone from "react-dropzone";
import AssetIcon from "./AssetIcon";
import request from "superagent";

export default class Upload extends Component {
    constructor(props) {
        super(props);

        this.state = {
            files: [],
            uploading: false,
        };

        this.onDrop = this.onDrop.bind(this);
        this.submitFiles = this.submitFiles.bind(this);
    }

    submitFiles() {
        const {files} = this.state;

        this.setState({
            uploading: true,
        });

        const formData = new FormData();
        files.forEach((file, i) => {
            formData.append(i, file)
        });

        // TODO
        const API_URL = 'http://localhost';

        const req = request
            .post(`${API_URL}/upload`);
        const uploadParams = {};
        const data = new FormData();
        files.forEach((file) => {
            data.append('file', file);
        });
        Object.keys(uploadParams).forEach(function (key) {
            data.append(key, uploadParams[key]);
        });

        this.req = req
            .on('progress', (e) => {
                this.setState({
                    progress: e.percent,
                });
            })
            .send(data)
            .end(() => {
                this.setState({
                    uploading: false,
                });
            });
    }

    onDrop(acceptedFiles) {
        const currentFiles = [...this.state.files, ...acceptedFiles];
        console.log('currentFiles', currentFiles);

        this.setState({
            files: currentFiles,
        });
    }

    renderFiles() {
        const {files} = this.state;

        return <div className="file-collection">
            {files.map((file, index) => {
                return <AssetIcon
                    key={index}
                    file={file}
                />
            })}
        </div>;
    }

    render() {
        const {files, uploading} = this.state;

        if (uploading) {
            return <div>
                {this.state.progress}%
            </div>;
        }

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

                <button
                    onClick={this.submitFiles}
                >
                    Next
                </button>
            </div>
        );
    }
}
