import React, {Component} from 'react';
import '../../scss/Upload.scss';
import PropTypes from "prop-types";
import {Button} from "react-bootstrap";

export default class UploadForm extends Component {
    render() {
        const {files} = this.props;

        return (
            <div className="container">
                <div className="App">
                    <header>
                        <h1>Uploader.</h1>
                    </header>
                    {files.length} selected files.

                    <Button onClick={this.props.onNext}>
                        Next
                    </Button>
                </div>
            </div>
        );
    }
}

UploadForm.propTypes = {
    files: PropTypes.array.isRequired,
    onNext: PropTypes.func.isRequired,
};

