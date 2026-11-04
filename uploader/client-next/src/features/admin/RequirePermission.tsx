'use client';

import {PropsWithChildren} from 'react';
import {useTranslation} from 'react-i18next';
import {Alert} from '@/components/ui/misc';
import {FullPageLoader} from '@/components/ui/loader';
import {Page} from '@/components/layout/AppShell';
import {getErrorMessage} from '@/lib/utils/errors';
import {useUploaderUser} from '@/features/user/useUploaderUser';
import type {UploaderUser} from '@/types/api';

/**
 * Hides an admin screen from the users the API would refuse anyway (it
 * enforces the permission on every call).
 */
export function RequirePermission({
    permission,
    children,
}: PropsWithChildren<{permission: keyof UploaderUser['permissions']}>) {
    const {t} = useTranslation();
    const {data: me, error, isPending} = useUploaderUser();

    if (isPending) {
        return <FullPageLoader />;
    }
    if (error || !me.permissions[permission]) {
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
