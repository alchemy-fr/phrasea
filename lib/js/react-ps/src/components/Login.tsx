import React, {FormEvent, useCallback, useState} from 'react';
import {useTranslation} from 'react-i18next';
import IdentityProviders, {IdentityProvidersProps} from "./IdentityProviders";
import OAuthClient from "../lib/oauth-client";
import {AxiosError} from "axios";

function LoginOr({label}: {
    label: string;
}) {
    return <div className="col-md-12">
        <div className="login-or text-center">
            <hr className="hr-or"/>
            <span className="span-or">
                {label}
            </span>
        </div>
    </div>
}

type Props = {
    oauthClient: OAuthClient;
    onLogin?: (data: any) => void;
    defaultHiddenForm?: boolean;
    externalIdpOnTop?: boolean;
} & IdentityProvidersProps;

export default function Login({
                                  onLogin,
                                  oauthClient,
                                  externalIdpOnTop = false,
                                  defaultHiddenForm = false,
                                  ...identityProvidersProps
                              }: Props) {
    const [loading, setLoading] = useState(false);
    const [username, setUsername] = useState('');
    const [password, setPassword] = useState('');
    const [errors, setErrors] = useState<string[]>([]);
    const [displayForm, setDisplayForm] = useState(!defaultHiddenForm);
    const {t} = useTranslation();

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
        {externalIdpOnTop && <>
            <div className="col-md-12">
                <div className="text-center">
                    <span className="span-or">
                        {t('login.sign_in_using', 'Sign In using') as string}
                    </span>
                </div>
            </div>
            <IdentityProviders
                {...identityProvidersProps}
            />
        </>}

        <div className="form-section">
            {externalIdpOnTop && <LoginOr label={t('login.or', 'or') as string}/>}
            {!displayForm && <>
                <div className={'form-group'}>
                    <button
                        className="btn btn-block btn-light"
                        onClick={() => setDisplayForm(true)}
                    >
                        {t('login.display_login_form', 'Sign in with login/password') as string}
                    </button>
                </div>
            </>}
            {(!defaultHiddenForm || displayForm) && <div
                className={'login-form'}
            >
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
                    {errors.length > 0 && <div className={'alert alert-danger'}>
                        <ul className="errors">
                            {errors.filter(e => e !== 'missing_access_token').map((e) => <li key={e}>{e}</li>)}
                        </ul>
                    </div>}
                    <div className="form-group">
                        <button
                            disabled={loading}
                            type={'submit'}
                            className={'btn btn-block btn-primary'}
                        >
                            {t('login.form.submit', 'Sign In') as string}
                        </button>
                    </div>
                </form>
            </div>}
        </div>

        {!externalIdpOnTop && <>
            <LoginOr label={t('login.or_sign_in_using', 'or Sign In using')}/>
            <IdentityProviders
                {...identityProvidersProps}
            />
        </>}
    </>
}
