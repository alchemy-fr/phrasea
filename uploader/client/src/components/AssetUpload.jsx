import React, {Component} from 'react';
// import PropTypes from 'prop-types'
import iconImg from '../images/asset-icon.svg';
import deleteImg from '../images/delete-button.svg';
import filesize from 'filesize';

export default class AssetUpload extends Component {
    // static propTypes = {
    //     file: PropTypes.object.isRequired,
    //     onUploadComplete: PropTypes.func,
    //     onUploadProgress: PropTypes.func,
    //     onRemove: PropTypes.func,
    // };

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
        this.onload = result => {
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

        if (file.type.indexOf('image/') === 0 && file.size < 15728640) {
            const reader = new FileReader();

            reader.onabort = () => console.log('file reading was aborted');
            reader.onerror = () => console.log('file reading has failed');
            reader.onload = result => {
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

    removeAsset = e => {
        e.stopPropagation();
        if (window.confirm('Delete?')) {
            this.props.onRemove && this.props.onRemove();
        }
    };

    render() {
        const {file, onRemove} = this.props;
        const {uploadProgress, ok} = this.state;

        let classes = ['file-icon'];
        if (ok) {
            classes.push('upload-ok');
        }

        return (
            <div className={classes.join(' ')} title={file.name}>
                {onRemove ? (
                    <div
                        title={'Remove'}
                        onClick={this.removeAsset}
                        className={'remove-file-btn'}
                    >
                        <img src={deleteImg} alt="Remove" />
                    </div>
                ) : (
                    ''
                )}
                <div className="size">{filesize(file.size)}</div>
                <div
                    className="file-progress"
                    style={{width: 100 - uploadProgress + '%'}}
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
