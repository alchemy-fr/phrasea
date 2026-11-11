'use client';

import {useRouter} from 'next/navigation';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {ListIcon} from 'lucide-react';
import {getEntityList} from '@/lib/api/metadata';
import {routes} from '@/lib/routes';
import {Button} from '@alchemy/next-ui/ui/button';
import {cn} from '@alchemy/next-ui/utils/cn';

/**
 * Link to the management of an entity list, shown to whoever may manage its
 * values. The list is loaded with the query of the list screens.
 */
export function ManageEntityListButton({
    listId,
    className,
}: {
    listId: string;
    className?: string;
}) {
    const {t} = useTranslation();
    const router = useRouter();
    const list = useQuery({
        queryKey: ['entity-list', listId],
        queryFn: () => getEntityList(listId),
    });

    if (!list.data?.capabilities?.editValues) {
        return null;
    }

    return (
        <Button
            variant="ghost"
            size="sm"
            className={cn('h-6 text-xs', className)}
            onClick={() => router.push(routes.entityListManage(listId))}
            data-testid="entity-list-manage"
        >
            <ListIcon /> {t('attribute.manage_list', 'Manage list')}
        </Button>
    );
}
