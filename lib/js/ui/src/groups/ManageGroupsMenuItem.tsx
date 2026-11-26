'use client';

import {useTranslation} from 'react-i18next';
import {UsersIcon} from 'lucide-react';
import {DropdownMenuItem} from '../ui/menu';
import {useModals} from '../modals/ModalProvider';
import {ManageGroupsDialog} from './ManageGroupsDialog';
import type {GroupsApi} from './types';

/**
 * The entry of the user menu opening the groups management. The client only
 * renders it for the users allowed to manage the groups (`canManageGroups`).
 */
export function ManageGroupsMenuItem({api}: {api: GroupsApi}) {
    const {t} = useTranslation();
    const {openModal} = useModals();

    return (
        <DropdownMenuItem
            data-testid="manage-groups"
            onSelect={() =>
                openModal(ManageGroupsDialog, {api}, {key: 'manage-groups'})
            }
        >
            <UsersIcon /> {t('user.manage_groups', 'Manage groups…')}
        </DropdownMenuItem>
    );
}
