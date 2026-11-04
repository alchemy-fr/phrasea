'use client';

import {useEffect, useRef} from 'react';
import {useQueryClient} from '@tanstack/react-query';
import {useAuth} from './AuthProvider';

/**
 * What the API answers depends on who asks (unlisted or disabled
 * publications, rights, details of a protected publication): the cached
 * answers are dropped whenever the user signs in or out. Renders nothing.
 */
export function SessionQueryReset() {
    const {status, user} = useAuth();
    const queryClient = useQueryClient();
    const userId = status === 'loading' ? undefined : (user?.id ?? '');
    const previous = useRef<string | undefined>(undefined);

    useEffect(() => {
        if (userId === undefined) {
            return;
        }
        if (previous.current !== undefined && previous.current !== userId) {
            void queryClient.invalidateQueries();
        }
        previous.current = userId;
    }, [userId, queryClient]);

    return null;
}
