'use client';

import {PropsWithChildren} from 'react';
import {useTranslation} from 'react-i18next';
import {Alert} from '@alchemy/next-ui/ui/misc';
import {FullPageLoader} from '@alchemy/next-ui/ui/loader';
import {Page} from '@/components/layout/AppShell';
import {getErrorMessage} from '@alchemy/next-ui/utils/errors';
import {useUploaderUser} from '@/features/user/useUploaderUser';
import type {UploaderUser} from '@/types/api';

type Permission = keyof UploaderUser['permissions'];

/**
 * Hides an admin screen from the users the API would refuse anyway (it
 * enforces the permission on every call). Given several permissions, any of
 * them grants access.
 */
export function RequirePermission({
    permission,
    children,
}: PropsWithChildren<{permission: Permission | Permission[]}>) {
    const {t} = useTranslation();
    const {data: me, error, isPending} = useUploaderUser();

    if (isPending) {
        return <FullPageLoader />;
    }
    const permissions = Array.isArray(permission) ? permission : [permission];
    if (error || !permissions.some(p => me.permissions[p])) {
        return (
            <Page>
                <Alert variant="destructive" data-testid="forbidden">
                    {error
                        ? getErrorMessage(error)
                        : t(
                              'admin.forbidden',
                              'You are not allowed to access this page.'
                          )}
                </Alert>
            </Page>
        );
    }

    return <>{children}</>;
}
