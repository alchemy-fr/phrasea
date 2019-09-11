import React, {Component} from 'react';
import '../../scss/Upload.scss';
import PropTypes from "prop-types";
import {Button} from "react-bootstrap";
import Container from "../Container";

export default class UploadDone extends Component {
    static propTypes = {
        goHome: PropTypes.func.isRequired,
    };

    render() {
        return <Container>
            <p>
                You're done!
            </p>

            <div>
                <Button onClick={this.props.goHome}>
                    Back to home
                </Button>
            </div>
        </Container>;
    }
}


