'use client';

import {ReactNode} from 'react';
import Link from 'next/link';
import {useQuery} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {ChevronRightIcon, PencilIcon} from 'lucide-react';
import {Button} from '@alchemy/ui/ui/button';
import {Alert, Badge, EmptyState} from '@alchemy/ui/ui/misc';
import {FullPageLoader} from '@alchemy/ui/ui/loader';
import {getErrorMessage} from '@alchemy/ui/utils/errors';
import {Page} from '@/components/layout/AppShell';
import {getAllTargets} from '@/lib/api/uploader';
import {targetsQueryKey} from '@/features/targets/TargetListScreen';
import type {Target} from '@/types/api';

/** A setting of the target, shown as a badge telling whether it is set */
export type AdminTargetBadge = {
    key: string;
    has: (target: Target) => boolean;
    label: string;
    notLabel: string;
};

/**
 * The targets, each with a badge per setting the user may edit, telling
 * whether the target has it, and a link to its editor.
 */
export function AdminTargetList({
    title,
    testId,
    badges,
    href,
    actions,
}: {
    title: string;
    testId: string;
    badges: AdminTargetBadge[];
    href: (target: Target) => string;
    actions?: ReactNode;
}) {
    const {t} = useTranslation();
    const {data, error, isPending} = useQuery({
        queryKey: targetsQueryKey,
        queryFn: getAllTargets,
    });

    return (
        <Page title={title} actions={actions}>
            {isPending ? (
                <FullPageLoader />
            ) : error ? (
                <Alert variant="destructive">{getErrorMessage(error)}</Alert>
            ) : data.length === 0 ? (
                <EmptyState
                    title={t('admin.targets.empty', 'No target yet.')}
                />
            ) : (
                <ul data-testid={testId} className="divide-y rounded-lg border">
                    {data.map(target => (
                        <li
                            key={target.id}
                            data-testid="admin-target-row"
                            className="flex items-center gap-3 px-4 py-3"
                        >
                            <div className="min-w-0 flex-1 truncate font-medium">
                                {target.name}
                            </div>
                            {badges.map(badge =>
                                badge.has(target) ? (
                                    <Badge
                                        key={badge.key}
                                        variant="secondary"
                                        data-testid="admin-target-badge"
                                        data-badge={badge.key}
                                        data-state="on"
                                    >
                                        {badge.label}
                                    </Badge>
                                ) : (
                                    <Badge
                                        key={badge.key}
                                        variant="muted"
                                        data-testid="admin-target-badge"
                                        data-badge={badge.key}
                                        data-state="off"
                                    >
                                        {badge.notLabel}
                                    </Badge>
                                )
                            )}
                            <Button variant="outline" size="sm" asChild>
                                <Link
                                    href={href(target)}
                                    data-testid="admin-target-edit"
                                >
                                    <PencilIcon /> {t('admin.edit', 'Edit')}
                                </Link>
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
        </Page>
    );
}

/** Where an admin screen stands: the list of the targets, then itself */
export function AdminBreadcrumb({
    href,
    label,
    current,
}: {
    /** The list of the targets */
    href: string;
    label: string;
    current: string;
}) {
    const {t} = useTranslation();

    return (
        <nav aria-label={t('admin.breadcrumb', 'Breadcrumb')}>
            <ol className="flex min-w-0 items-center gap-1 text-sm text-muted-foreground">
                <li className="shrink-0">
                    <Link
                        href={href}
                        data-testid="admin-back"
                        className="hover:text-foreground hover:underline"
                    >
                        {label}
                    </Link>
                </li>
                <li aria-hidden className="shrink-0">
                    <ChevronRightIcon className="size-4" />
                </li>
                <li
                    aria-current="page"
                    data-testid="admin-breadcrumb-current"
                    className="min-w-0 truncate text-foreground"
                >
                    {current}
                </li>
            </ol>
        </nav>
    );
}
