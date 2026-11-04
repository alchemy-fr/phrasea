'use client';

import Link from 'next/link';
import {useTranslation} from 'react-i18next';
import {BookImageIcon} from 'lucide-react';
import type {Publication, PublicationRef} from '@/types/api';
import {useTranslateField} from '@/lib/utils/translate';
import {routes} from '@/lib/routes';
import {cn} from '@alchemy/next-ui/utils/cn';

/**
 * Where the publication is in its tree: its parent, itself and its children
 * (those the user may read). Every node leads to its publication.
 */
export function PublicationTree({
    publication,
    onNavigate,
}: {
    publication: Publication;
    onNavigate?: () => void;
}) {
    const {t} = useTranslation();
    const translate = useTranslateField();
    const label = (p: PublicationRef) =>
        translate(p, 'title') || p.slug || p.id;
    const children = publication.children ?? [];

    const node = (p: PublicationRef, current = false) => (
        <Link
            href={routes.publication(p)}
            onClick={onNavigate}
            aria-current={current ? 'page' : undefined}
            className={cn(
                'flex min-h-8 items-center gap-2 rounded-md px-2 py-1 text-sm hover:bg-accent [&_svg]:size-4 [&_svg]:shrink-0 [&_svg]:text-muted-foreground',
                current && 'bg-accent font-semibold'
            )}
        >
            <BookImageIcon />
            <span className="min-w-0 truncate">{label(p)}</span>
        </Link>
    );

    const currentNode = (
        <li>
            {node(publication, true)}
            {children.length > 0 ? (
                <ul className="ml-4 border-l pl-2">
                    {children.map(child => (
                        <li key={child.id}>{node(child)}</li>
                    ))}
                </ul>
            ) : null}
        </li>
    );

    return (
        <nav
            data-testid="publication-tree"
            aria-label={t('publication.tree', 'Publications')}
        >
            <ul>
                {publication.parent ? (
                    <li>
                        {node(publication.parent)}
                        <ul className="ml-4 border-l pl-2">{currentNode}</ul>
                    </li>
                ) : (
                    currentNode
                )}
            </ul>
        </nav>
    );
}
