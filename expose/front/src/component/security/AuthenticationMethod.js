import React, {PureComponent} from 'react';
import {PropTypes} from 'prop-types';
import apiClient from "../../lib/apiClient";
import config from "../../lib/config";

class AuthenticationMethod extends PureComponent {
    static propTypes = {
        onAuthorization: PropTypes.func.isRequired,
        error: PropTypes.string,
    };

    state = {
        username: '',
        password: '',
        loading: false,
        errors: [],
    };

    onSubmit = async (e) => {
        e.preventDefault();

        this.setState({loading: true}, async () => {
            const {username, password} = this.state;
            const newState = {loading: false};

            try {
                const res = await apiClient.post(`${config.getAuthBaseUrl()}/oauth/v2/token`, {
                    username,
                    password,
                    grant_type: 'password',
                    client_id: config.get('CLIENT_ID'),
                    client_secret: config.get('CLIENT_SECRET'),
                });

                if (res.access_token) {
                    newState.errors = [];
                    this.props.onAuthorization(`Bearer ${res.access_token}`);
                } else {
                    newState.errors = [res.error_description];
                }
            } catch (err) {
                newState.errors = [err.response.body.error_description];
            }

            this.setState(newState);
        });
    };

    render() {
        const {error} = this.props;
        const {loading, username, password, errors} = this.state;

        if (error) {
            errors.push(error);
        }

        return <div className={'container'}>
            <form
                onSubmit={this.onSubmit}
            >
                <div className="form-group">
                    <label htmlFor="username">
                        Username
                    </label>
                    <input
                        className={'form-control'}
                        id={'username'}
                        disabled={loading}
                        value={username}
                        onChange={e => this.setState({username: e.target.value})}
                        type="text"
                    />
                </div>
                <div className="form-group">
                    <label htmlFor="password">
                        Password
                    </label>
                    <input
                        className={'form-control'}
                        id={'password'}
                        disabled={loading}
                        value={password}
                        onChange={e => this.setState({password: e.target.value})}
                        type="password"
                    />
                </div>
                {errors.length > 0 ? <ul className="errors">
                    {errors.filter(e => e !== 'missing_access_token').map((e) => <li key={e}>{e}</li>)}
                </ul> : ''}
                <button
                    disabled={loading}
                    type={'submit'}
                    className={'btn btn-primary'}
                >
                    OK
                </button>
            </form>
        </div>
    }
}

export default AuthenticationMethod;
