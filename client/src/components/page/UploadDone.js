import React, {Component} from 'react';
import '../../scss/Upload.scss';
import PropTypes from "prop-types";
import {Button} from "react-bootstrap";

export default class UploadDone extends Component {
    render() {
        return (
            <div className="container">
                <div className="App">
                    <header>
                        <h1>Uploader.</h1>
                    </header>
                    <p>
                        You're done!
                    </p>

                    <div>
                        <Button onClick={this.props.goHome}>
                            Back to home
                        </Button>
                    </div>
                </div>
            </div>
        );
    }
}

UploadDone.propTypes = {
    goHome: PropTypes.func.isRequired,
};


