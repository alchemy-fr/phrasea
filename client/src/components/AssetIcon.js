import React, {Component} from 'react';
import PropTypes from 'prop-types'
import iconImg from '../images/asset-icon.svg';

export default class AssetIcon extends Component {
    constructor(props) {
        super(props);

        this.state = {
            src: null,
        };

        this.load();
    }

    load() {
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

        return (
            <div className="file-icon" title={file.name}>
                <img
                    className="img-fluid"
                    src={this.state.src}
                    alt={file.name}
                />
                <br/>
                {file.type}
            </div>
        );
    }
}

AssetIcon.propTypes = {
    file: PropTypes.object.isRequired,
};
