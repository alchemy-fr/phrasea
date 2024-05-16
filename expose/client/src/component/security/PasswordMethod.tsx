import React, {FormEvent} from 'react';
import {storePassword} from '../../lib/credential';
import {useTranslation} from 'react-i18next';

type Props = {
    onAuthorization: () => void;
    authorization?: string;
    securityContainerId: string;
    error?: string;
};

export default function PasswordMethod({
    securityContainerId,
    onAuthorization,
    error,
}: Props) {
    const [password, setPassword] = React.useState('');
    const {t} = useTranslation();
    const onSubmit = (e: FormEvent) => {
        e.preventDefault();

        storePassword(securityContainerId, password);
        onAuthorization();
    };

    const errors: Record<string, string> = {
        'invalid_password': t('error.invalid_password', 'Invalid password'),
    }

    const translatedError = error ? (errors[error] ?? error) : undefined;

    return (
        <div className={'container'}>
            <form onSubmit={onSubmit}>
                <div className="form-group">
                    <label htmlFor="password">{t('publication.password_required.enter_password', `Enter password`)}</label>
                    <input
                        className={'form-control'}
                        id={'password'}
                        value={password}
                        onChange={e => setPassword(e.target.value)}
                        type="password"
                    />
                </div>
                {translatedError && error !== 'missing_password' ? (
                    <ul className="errors">
                        <li>{translatedError}</li>
                    </ul>
                ) : (
                    ''
                )}
                <button type={'submit'} className={'btn btn-primary'}>
                    {t('publication.password_required.submit', `OK`)}
                </button>
            </form>
        </div>
    );
}
