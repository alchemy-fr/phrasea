'use client';

import {useQuery} from '@tanstack/react-query';
import {AppRole, useAuth} from '@/lib/auth/AuthProvider';
import {
    getProfiles,
    getPublications,
    profileKeys,
    publicationKeys,
} from '@/lib/api/publications';

export type AdminAccess = {
    /** The user can edit some publications */
    publications: boolean;
    /** The user can see some profiles */
    profiles: boolean;
};

/**
 * Which administration screens the user has something to do in. The rights
 * are ACLs on the API side: besides the administrators, a user is offered
 * the screens where the API lists something for them.
 */
export function useAdminAccess(): AdminAccess {
    const {isAuthenticated, hasRole} = useAuth();
    const isAdmin =
        isAuthenticated &&
        (hasRole(AppRole.ExposeAdmin) || hasRole(AppRole.Admin));
    const probe = isAuthenticated && !isAdmin;

    const publications = useQuery({
        queryKey: publicationKeys.list({editable: true, flatten: true}),
        queryFn: () => getPublications({editable: true, flatten: true}),
        enabled: probe,
        select: page => page.total > 0,
    });
    const profiles = useQuery({
        queryKey: profileKeys.list(),
        queryFn: () => getProfiles(),
        enabled: probe,
        select: page => page.total > 0,
    });

    return {
        publications: isAdmin || !!publications.data,
        profiles: isAdmin || !!profiles.data,
    };
}
