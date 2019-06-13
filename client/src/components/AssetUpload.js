import React, {Component} from 'react';
import PropTypes from 'prop-types'
import iconImg from '../images/asset-icon.svg';

export default class AssetUpload extends Component {
    state = {
        src: null,
        uploadProgress: 0,
        ok: false,
    };

    onload = null;

    setUploadProgress(progress, ok) {
        this.setState({
            uploadProgress: progress,
            ok,
        });
    }

    componentDidMount() {
        this.onload = (result) => {
            this.setState({
                src: result.target.result,
            });
        };
        this.loadIcon();
    }

    componentWillUnmount() {
        this.onload = null;
    }

    loadIcon() {
        const {file} = this.props;

        if (file.type.indexOf('image/') === 0) {
            const reader = new FileReader();

            reader.onabort = () => console.log('file reading was aborted');
            reader.onerror = () => console.log('file reading has failed');
            reader.onload = (result) => {
                if (this.onload) {
                    this.onload(result);
                }
            };
            reader.readAsDataURL(file);
        }

        this.setState({
            src: iconImg,
        });
    }

    render() {
        const {file} = this.props;
        const {uploadProgress, ok} = this.state;

        let classes = ['file-icon'];
        if (ok) {
            classes.push('upload-ok');
        }

        return (
            <div className={classes.join(' ')} title={file.name}>
                <div className="file-progress"
                     style={{width: (100 - uploadProgress) + '%'}}
                />
                <img
                    className="img-fluid"
                    src={this.state.src}
                    alt={file.name}
                />
            </div>
        );
    }
}

AssetUpload.propTypes = {
    file: PropTypes.object.isRequired,
    onUploadComplete: PropTypes.func,
    onUploadProgress: PropTypes.func,
};
