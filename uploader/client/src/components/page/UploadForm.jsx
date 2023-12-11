import React, {Component} from 'react';
import '../../scss/Upload.scss';
// import PropTypes from "prop-types";
import AssetForm from '../AssetForm';

export default class UploadForm extends Component {
    // static propTypes = {
    //     files: PropTypes.array.isRequired,
    //     onNext: PropTypes.func.isRequired,
    //     onCancel: PropTypes.func,
    //     targetId: PropTypes.string.isRequired,
    // };

    onComplete = data => {
        this.props.onNext(data);
    };

    render() {
        const {files, targetId} = this.props;

        return (
            <>
                <p>{files.length} selected files.</p>

                <AssetForm
                    targetId={targetId}
                    submitPath={'/form/validate'}
                    onComplete={this.onComplete}
                    onCancel={this.props.onCancel}
                />
            </>
        );
    }
}
