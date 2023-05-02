import React, {PureComponent} from 'react';
import {PropTypes} from 'prop-types';
import {setPassword} from "../../lib/credential";

class PasswordMethod extends PureComponent {
    static propTypes = {
        onAuthorization: PropTypes.func.isRequired,
        authorization: PropTypes.string,
        securityContainerId: PropTypes.string.isRequired,
        error: PropTypes.string,
    };

    state = {
        password: '',
    };

    onSubmit = (e) => {
        e.preventDefault();

        setPassword(this.props.securityContainerId, this.state.password);
        this.props.onAuthorization();
    };

    render() {
        const {error} = this.props;

        return <div className={'container'}>
            <form
                onSubmit={this.onSubmit}
            >
                <div className="form-group">
                    <label htmlFor="password">
                        Enter password
                    </label>
                    <input
                        className={'form-control'}
                        id={'password'}
                        value={this.state.password}
                        onChange={e => this.setState({password: e.target.value})}
                        type="password"
                    />
                </div>
                {error && error !== 'missing_password' ? <ul className="errors">
                    <li>{error}</li>
                </ul> : ''}
                <button
                    type={'submit'}
                    className={'btn btn-primary'}
                >
                    OK
                </button>
            </form>
        </div>
    }
}

export default PasswordMethod;
