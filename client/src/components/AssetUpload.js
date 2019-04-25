import React, {Component} from 'react';
import PropTypes from 'prop-types'
import iconImg from '../images/asset-icon.svg';
import request from "superagent";
import config from '../store/config'
import auth from '../store/auth'

export default class AssetUpload extends Component {
    constructor(props) {
        super(props);

        this.state = {
            src: null,
            uploadProgress: null,
            id: null,
        };
    }

    componentDidMount() {
        this.loadIcon();
    }

    getBytesLoaded() {
        return this.state.uploadProgress / 100 * this.props.file.size;
    }

    upload() {
        this.setState({
            uploadProgress: 0,
        });

        const formData = new FormData();
        formData.append('file', this.props.file);

        const accessToken = auth.getAccessToken();

        request
            .post(config.getUploadBaseURL() + '/assets')
            .accept('json')
            .set('Authorization', `Bearer ${accessToken}`)
            .on('progress', (e) => {
                if (e.direction !== 'upload') {
                    return;
                }
                this.setState({
                    uploadProgress: e.percent,
                }, () => this.props.onUploadProgress());
            })
            .send(formData)
            .end((err, res) => {
                if (err) {
                    console.error(err);
                    this.props.onUploadComplete();
                    return;

                }

                if (res.ok) {
                    this.setState({
                        id: res.body.id,
                        uploadProgress: 100,
                    }, () => this.props.onUploadComplete());
                }
            });
    }

    loadIcon() {
        const {file} = this.props;

        if (file.type.indexOf('image/') === 0) {
            const reader = new FileReader();

            reader.onabort = () => console.log('file reading was aborted');
            reader.onerror = () => console.log('file reading has failed');
            reader.onload = (result) => {
                this.setState({
                    src: result.target.result,
                });
            };
            reader.readAsDataURL(file);
        }

        this.setState({
            src: iconImg,
        });
    }

    render() {
        const {file} = this.props;
        const {uploadProgress} = this.state;

        return (
            <div className="file-icon" title={file.name}>
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
    onUploadComplete: PropTypes.func.isRequired,
    onUploadProgress: PropTypes.func.isRequired,
};
