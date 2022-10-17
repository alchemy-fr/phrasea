import React, {FormEvent, useCallback, useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import IdentityProviders, {IdentityProvidersProps} from "./IdentityProviders";
import OAuthClient from "../lib/oauth-client";
import {AxiosError} from "axios";

type Props = {
    clientId: string;
    clientSecret: string;
    onLogin?: (data: any) => void;
} & IdentityProvidersProps;

export default function Login({
                                  onLogin,
                                  clientId,
                                  clientSecret,
                                  ...identityProvidersProps
                              }: Props) {
    const [loading, setLoading] = useState(false);
    const [username, setUsername] = useState('');
    const [password, setPassword] = useState('');
    const [errors, setErrors] = useState<string[]>([]);
    const {t} = useTranslation();

    const oauthClient = useMemo(() => new OAuthClient({
        clientId,
        clientSecret,
        baseUrl: identityProvidersProps.authBaseUrl,
    }), [
        clientId,
        clientSecret,
        identityProvidersProps.authBaseUrl,
    ]);

    const onSubmit = useCallback(async (e: FormEvent) => {
        e.preventDefault();
        setLoading(true);

        try {
            const res = await oauthClient.login(username, password);
            onLogin && onLogin(res);
        } catch (e: any) {
            if (e instanceof AxiosError) {
                if (e.response) {
                    setErrors([e.response.data.error_description as string]);
                }
            }
            setLoading(false);
        }
    }, [setLoading, setErrors, oauthClient, username, password]);

    return <>
        <form
            onSubmit={onSubmit}
        >
            <div className="form-group">
                <label htmlFor="username">
                    {t('login.form.username', 'Username') as string}
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
                    {t('login.form.password', 'Password') as string}
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
                {t('login.form.submit', 'Sign in') as string}
            </button>
        </form>

        <IdentityProviders
            {...identityProvidersProps}
        />
    </>
}
