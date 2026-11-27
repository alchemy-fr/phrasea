'use client';

import {useId, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useMutation} from '@tanstack/react-query';
import {LockIcon} from 'lucide-react';
import {Button} from '@alchemy/ui/ui/button';
import {FormRow, Input} from '@alchemy/ui/ui/input';
import {isApiError} from '@/lib/api/http';
import {type ShareAccess, unlockShare} from '@/lib/api/misc';

/**
 * The password of a protected share, asked before anything of the share is
 * loaded (neither its name nor its logo can be read without it).
 */
export function SharePasswordForm({
    id,
    token,
    onUnlock,
}: {
    id: string;
    token: string;
    onUnlock: (access: ShareAccess) => void;
}) {
    const {t} = useTranslation();
    const inputId = useId();
    const [password, setPassword] = useState('');
    const unlock = useMutation({
        mutationFn: () => unlockShare(id, token, password),
        onSuccess: access => onUnlock(access),
    });

    const error = unlock.error;
    const errorMessage = !error
        ? undefined
        : isApiError(error, 429)
          ? t(
                'share.password.too_many_attempts',
                'Too many attempts. Please try again later.'
            )
          : isApiError(error, 403)
            ? t('share.password.invalid', 'Wrong password')
            : t(
                  'share.password.error',
                  'The password could not be checked. Please try again.'
              );

    return (
        <div className="flex flex-1 items-center justify-center p-4">
            <form
                data-testid="share-password-form"
                className="w-full max-w-sm space-y-4 rounded-lg border bg-card p-6 shadow-sm"
                onSubmit={e => {
                    e.preventDefault();
                    if (password) {
                        unlock.mutate();
                    }
                }}
            >
                <div className="flex flex-col items-center gap-2 text-center">
                    <span className="flex size-10 items-center justify-center rounded-full bg-muted">
                        <LockIcon className="size-5" />
                    </span>
                    <h1 className="text-lg font-semibold">
                        {t('share.password.title', 'Protected link')}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {t(
                            'share.password.intro',
                            'Enter the password to access the shared content.'
                        )}
                    </p>
                </div>
                <FormRow
                    label={t('share.password.label', 'Password')}
                    htmlFor={inputId}
                    error={errorMessage}
                    className="mb-0"
                >
                    <Input
                        id={inputId}
                        type="password"
                        autoComplete="current-password"
                        autoFocus
                        value={password}
                        aria-invalid={errorMessage ? true : undefined}
                        onChange={e => setPassword(e.target.value)}
                    />
                </FormRow>
                <Button
                    type="submit"
                    className="w-full"
                    loading={unlock.isPending}
                    disabled={!password}
                >
                    {t('share.password.submit', 'Open')}
                </Button>
            </form>
        </div>
    );
}
