import React, {PureComponent} from 'react';
import {PropTypes} from 'prop-types';
import config from "../../lib/config";
import{OAuthProviders} from '@alchemy-fr/phraseanet-react-components';
import {oauthClient, setAuthRedirect} from "../../lib/oauth";

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

            try {
                await oauthClient.login(username, password);
                this.props.onAuthorization();
            } catch (e) {
                const newState = {loading: false};
                if (e.res) {
                    newState.errors = [e.res.body.error_description];
                } else {
                    throw e;
                }
                this.setState(newState);
            }
        });
    };

    componentDidMount() {
        setAuthRedirect(document.location.pathname);
    }

    render() {
        const {error} = this.props;
        const {loading, username, password, errors} = this.state;

        const allErrors = [...errors];
        if (error) {
            allErrors.push(error);
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
                {allErrors.length > 0 ? <ul className="errors">
                    {allErrors.filter(e => e !== 'missing_access_token').map((e) => <li key={e}>{e}</li>)}
                </ul> : ''}
                <button
                    disabled={loading}
                    type={'submit'}
                    className={'btn btn-primary'}
                >
                    OK
                </button>
            </form>

            <OAuthProviders
                authBaseUrl={config.getAuthBaseUrl()}
                authClientId={config.getClientCredential().clientId}
                providers={config.get('identityProviders')}
            />
        </div>
    }
}

export default AuthenticationMethod;
