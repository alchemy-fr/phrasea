import React, {Component} from 'react';
import {Button, FormControl, FormGroup, FormLabel} from "react-bootstrap";
import {Download as DownloadAction} from "../actions/download";

export default class Download extends Component {
    constructor(props) {
        super(props);

        this.state = {
            url: '',
            done: false,
            loading: false,
        };
    }

    isFormValid() {
        return this.state.url.length > 0;
    }

    handleSubmit = event => {
        event.preventDefault();

        this.setState({
            loading: true,
        });
        DownloadAction(this.state.url, () => {
            this.setState({
                done: true,
                loading: false,
                url: '',
            });

            setTimeout(() => {
                this.setState({
                    done: false,
                });
            }, 3000);
        }, () => {
            this.setState({
                loading: false,
            });
        });
    };

    handleChange = event => {
        this.setState({
            [event.target.id]: event.target.value
        });
    };

    render() {
        const {done} = this.state;

        return (
            <form onSubmit={this.handleSubmit}>
                <FormGroup controlId="url">
                    <FormLabel>URL</FormLabel>
                    <FormControl
                        disabled={this.state.loading}
                        type="text"
                        value={this.state.url}
                        onChange={this.handleChange}
                    />
                </FormGroup>
                <Button
                    disabled={this.state.loading || this.state.done || !this.isFormValid()}
                    type="submit"
                >
                    Upload this URL
                </Button>
                {done ? (<span>
                        {' '}
                        <span className="badge badge-success">done!</span>
                        </span>
                ) : ''}
            </form>
        );
    }
}
