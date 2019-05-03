import React, {Component} from 'react';
import '../../scss/Upload.scss';
import AssetUpload from "../AssetUpload";
import PropTypes from "prop-types";
import uploadBatch from "../../store/upload";

export default class UploadProgress extends Component {
    fileRefs = {};

    constructor(props) {
        super(props);

        this.state = {
            progress: 0,
        }
    }

    renderProgressBar() {
        const {
            progress
        } = this.state;

        return <div className="progress">
            <div className="progress-bar"
                 role="progressbar"
                 style={{width: progress + '%'}}
                 aria-valuenow={progress}
                 aria-valuemin="0"
                 aria-valuemax="100"
            />
        </div>;
    }

    renderFiles() {
        const {files} = this.props;

        return <div className="file-collection">
            {files.map((file, index) => {
                return <AssetUpload
                    key={index}
                    file={file}
                    ref={(ref) => this.fileRefs[index] = ref}
                />
            })}
        </div>;
    }

    componentDidMount() {
        uploadBatch.registerProgressHandler((e) => {
            this.setState({
                progress: e.totalPercent,
            });

            this.fileRefs[e.index].setUploadProgress(e.filePercent, false);
        });
        uploadBatch.registerFileCompletehandler((err, res, index) => {
            this.fileRefs[index].setUploadProgress(100, false);
            this.setState({
                progress: 100,
            });
        });
        uploadBatch.registerCompletehandler(() => {
            this.setState({
                progress: 100,
            }, () => {
                this.props.onNext();
            });
        });
        uploadBatch.startUpload();
    }

    render() {
        const {files} = this.props;

        return (
            <div className="container">
                <div className="App">
                    <header>
                        <h1>Uploader.</h1>
                    </header>
                    <p>
                        {files.length} selected files.
                    </p>
                    <div>
                    {this.renderFiles()}
                    {this.renderProgressBar()}
                    </div>
                </div>
            </div>
        );
    }
}

UploadProgress.propTypes = {
    files: PropTypes.array.isRequired,
    onNext: PropTypes.func.isRequired,
};


