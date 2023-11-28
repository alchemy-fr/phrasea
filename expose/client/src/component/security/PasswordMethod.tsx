import React, {FormEvent} from 'react'
import { storePassword } from '../../lib/credential'

type Props = {
    onAuthorization: () => void,
    authorization?: string,
    securityContainerId: string,
    error?: string,
};

export default function PasswordMethod({
    securityContainerId,
    onAuthorization,
    error,
}: Props) {
    const [password, setPassword] = React.useState('');

    const onSubmit = (e: FormEvent) => {
        e.preventDefault()

        storePassword(securityContainerId, password);
        onAuthorization();
    }

    return <div className={'container'}>
        <form onSubmit={onSubmit}>
            <div className="form-group">
                <label htmlFor="password">Enter password</label>
                <input
                    className={'form-control'}
                    id={'password'}
                    value={password}
                    onChange={(e) =>
                        setPassword(e.target.value)
                    }
                    type="password"
                />
            </div>
            {error && error !== 'missing_password' ? (
                <ul className="errors">
                    <li>{error}</li>
                </ul>
            ) : (
                ''
            )}
            <button type={'submit'} className={'btn btn-primary'}>
                OK
            </button>
        </form>
    </div>
}
