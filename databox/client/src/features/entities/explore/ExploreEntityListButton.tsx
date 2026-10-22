'use client';

import Link from 'next/link';
import {useTranslation} from 'react-i18next';
import {ChevronDownIcon, CompassIcon} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/menu';
import {WorkspaceName} from '@/features/collections/WorkspaceName';
import {routes} from '@/lib/routes';
import {cn} from '@/lib/utils/cn';
import type {EntityListRef} from './useEntityListsOfFacet';

type Props = {
    /** Smaller, for the facets and the pickers */
    compact?: boolean;
    className?: string;
    /** Called when leaving for the exploration (to close the overlay it sits in) */
    onNavigate?: () => void;
} & (
    | {listId: string; lists?: never}
    | {
          /** Several lists (one per workspace): a menu to pick one */
          lists: EntityListRef[];
          listId?: never;
      }
);

/** Opens the exploration of the values of an entity list */
export function ExploreEntityListButton({
    compact,
    className,
    onNavigate,
    ...props
}: Props) {
    const {t} = useTranslation();
    const lists = props.lists ?? [];
    const listId =
        props.listId ?? (lists.length === 1 ? lists[0].listId : undefined);
    const label = t('entity_list.explore', 'Explore');
    const buttonClass = cn(compact && 'h-6 gap-1 px-2 text-xs', className);

    if (listId) {
        return (
            <Button asChild variant="ghost" size="sm" className={buttonClass}>
                <Link
                    href={routes.entityListExplore(listId)}
                    data-testid="entity-list-explore"
                    onClick={e => {
                        e.stopPropagation();
                        onNavigate?.();
                    }}
                >
                    <CompassIcon /> {label}
                </Link>
            </Button>
        );
    }

    if (lists.length === 0) {
        return null;
    }

    return (
        <ExploreListsMenu
            lists={lists}
            label={label}
            className={buttonClass}
            onNavigate={onNavigate}
        />
    );
}

function ExploreListsMenu({
    lists,
    label,
    className,
    onNavigate,
}: {
    lists: EntityListRef[];
    label: string;
    className?: string;
    onNavigate?: () => void;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="sm"
                    className={className}
                    data-testid="entity-list-explore"
                >
                    <CompassIcon /> {label}
                    <ChevronDownIcon className="opacity-60" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {lists.map(l => (
                    <DropdownMenuItem key={l.listId} asChild>
                        <Link
                            href={routes.entityListExplore(l.listId)}
                            data-testid="entity-list-explore-item"
                            onClick={() => onNavigate?.()}
                        >
                            <WorkspaceName id={l.workspaceId} />
                        </Link>
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
