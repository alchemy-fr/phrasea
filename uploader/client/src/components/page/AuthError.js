import React, {Component} from 'react';
import Container from "../Container";

export default class AuthError extends Component {
    render() {
        return (
            <Container title="Authentication error">
                An error has occured while authenticating.
            </Container>
        );
    }
}
