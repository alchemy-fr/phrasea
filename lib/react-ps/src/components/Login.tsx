import React, {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {IdentityProvidersProps} from "./IdentityProviders";

type Props = {} & IdentityProvidersProps;

export default function Login({
    ...identityProviderProps
                              }: Props) {
    const [loading, setLoading] = useState(false);
    const [username, setUsername] = useState('');
    const [password, setPassword] = useState('');
    const [errors, setErrors] = useState([]);

    const {t} = useTranslation();

    const onSubmit = (e: FormEvent) => {

    };

    return <>
        <form
            onSubmit={onSubmit}
        >
            <div className="form-group">
                <label htmlFor="username">
                    {t('login.form.username', 'Username')}
                </label>
                <input
                    className={'form-control'}
                    id={'username'}
                    disabled={loading}
                    value={username}
                    onChange={e => setUsername(e.target.value)}
                    type="text"
                />
            </div>
            <div className="form-group">
                <label htmlFor="password">
                    {t('login.form.password', 'Password')}
                </label>
                <input
                    className={'form-control'}
                    id={'password'}
                    disabled={loading}
                    value={password}
                    onChange={e => setPassword(e.target.value)}
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
                {t('login.form.submit', 'Sign in')}
            </button>
        </form>

        <OAuthProviders
            {...identityProviderProps}
        />
    </>
}
