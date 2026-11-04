'use client';

import {useQuery} from '@tanstack/react-query';
import {useAuth} from '@/lib/auth/AuthProvider';
import {getMe} from '@/lib/api/uploader';

export const meQueryKey = ['me'] as const;

/**
 * The uploader profile of the signed-in user (`GET /me`): what the menu
 * offers depends on its permissions. The API enforces them anyway.
 */
export function useUploaderUser() {
    const {isAuthenticated, user} = useAuth();

    return useQuery({
        queryKey: [...meQueryKey, user?.id],
        queryFn: getMe,
        enabled: isAuthenticated,
        staleTime: 5 * 60 * 1000,
    });
}
