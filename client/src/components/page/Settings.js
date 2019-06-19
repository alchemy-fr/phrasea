import React, {Component} from 'react';
import ChangePassword from "./ChangePassword";
import Container from "../Container";

export default class Settings extends Component {
    render() {
        return (
            <Container title="Settings">
                <ChangePassword />
            </Container>
        );
    }
}
