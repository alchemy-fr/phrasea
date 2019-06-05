import React, {Component} from 'react';
import {FormControl, FormGroup, FormLabel} from "react-bootstrap";

export default class URLInput extends Component {
    state = {
        url: '',
    };

    isFormValid() {
        return this.state.url.length > 0;
    }

    handleChange = event => {
        this.setState({
            [event.target.id]: event.target.value
        });

        this.props.onChange(event.target.value);
    };

    render() {
        const {url} = this.state;

        return (
            <FormGroup controlId="url">
                <FormLabel>URL</FormLabel>
                <FormControl
                    type="text"
                    value={url}
                    onChange={this.handleChange}
                />
            </FormGroup>
        );
    }
}
