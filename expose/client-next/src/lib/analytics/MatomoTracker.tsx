'use client';

import {MatomoTracker as BaseMatomoTracker} from '@alchemy/next-core/analytics/MatomoTracker';
import {useConfig} from '@/lib/config/ConfigProvider';
import {useAuth} from '@/lib/auth/AuthProvider';

export function MatomoTracker() {
    const {user, status} = useAuth();

    return (
        <BaseMatomoTracker
            matomo={useConfig().analytics.matomo}
            userId={user?.id}
            authResolved={status !== 'loading'}
        />
    );
}
