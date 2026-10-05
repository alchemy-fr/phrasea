'use client';

import {useTranslation} from 'react-i18next';
import {GlobeIcon, UsersIcon} from 'lucide-react';
import type {DisplayProfile} from '@/types/api';

/** Public, or shared by someone else: a hint next to the profile name */
export function ProfileOwnership({
    profile,
    userId,
}: {
    profile: DisplayProfile;
    userId: string | undefined;
}) {
    const {t} = useTranslation();
    const shared = !!profile.owner && profile.owner.id !== userId;

    if (profile.public) {
        return (
            <GlobeIcon
                className="size-3.5 text-muted-foreground"
                aria-label={t('common.public', 'Public')}
            />
        );
    }
    if (shared) {
        return (
            <UsersIcon
                className="size-3.5 text-muted-foreground"
                aria-label={t('profile.shared_by', 'Shared by {{owner}}', {
                    owner: profile.owner?.username,
                })}
            />
        );
    }

    return null;
}
