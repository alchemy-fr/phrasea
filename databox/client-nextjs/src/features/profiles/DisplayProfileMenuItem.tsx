'use client';

import {useEffect} from 'react';
import {useTranslation} from 'react-i18next';
import {LayoutTemplateIcon} from 'lucide-react';
import {DropdownMenuItem} from '@/components/ui/menu';
import {useModals} from '@/components/modals/ModalProvider';
import {useProfileStore} from './profileStore';
import {SelectProfileDialog} from './SelectProfileDialog';

export function DisplayProfileMenuItem() {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const current = useProfileStore(s => s.current);
    const load = useProfileStore(s => s.load);

    useEffect(() => {
        void load();
    }, [load]);

    return (
        <DropdownMenuItem onSelect={() => openModal(SelectProfileDialog, {})}>
            <LayoutTemplateIcon />
            <span className="flex min-w-0 flex-col">
                <span className="text-xs text-muted-foreground">
                    {t('profile.display_profile', 'Display profile')}
                </span>
                <span className="truncate">
                    {current?.name ??
                        t('profile.default', 'Default display profile')}
                </span>
            </span>
        </DropdownMenuItem>
    );
}
