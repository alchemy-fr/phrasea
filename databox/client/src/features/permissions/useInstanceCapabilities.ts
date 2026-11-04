'use client';

import {useQuery} from '@tanstack/react-query';
import {getInstanceCapabilities} from '@/lib/api/misc';
import {useAuth} from '@/lib/auth/AuthProvider';
import type {InstanceCapabilities} from '@/types/api';

export const instanceCapabilitiesQueryKey = ['instance-capabilities'];

const none: InstanceCapabilities = {
    createWorkspace: false,
    createProfile: false,
    shareBasket: false,
    publishSavedSearch: false,
};

/**
 * Capabilities of the current user granted outside any workspace. Everything
 * is denied while loading and for anonymous users.
 */
export function useInstanceCapabilities(): InstanceCapabilities {
    const {isAuthenticated} = useAuth();
    const {data} = useQuery({
        queryKey: instanceCapabilitiesQueryKey,
        queryFn: getInstanceCapabilities,
        enabled: isAuthenticated,
        staleTime: 10 * 60 * 1000,
    });

    return (isAuthenticated ? data : undefined) ?? none;
}
